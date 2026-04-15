<?php
/**
 * Runtime tests for v1.0.2 fixes (F1, F6).
 *
 * Standalone PHP script (no WordPress required). Mocks WordPress functions used by
 * the touched code paths and exercises the targeted branches with assertions.
 *
 * Usage:
 *   php tests/runtime-test-v1.0.2.php
 *
 * Exit code 0 = all assertions passed, 1 = at least one assertion failed.
 */

declare( strict_types=1 );

// -----------------------------------------------------------------------------
// Minimal WordPress function shims (only what's needed by the touched methods)
// -----------------------------------------------------------------------------

if ( ! function_exists( 'wp_parse_url' ) ) {
    function wp_parse_url( string $url, int $component = -1 ) {
        return parse_url( $url, $component );
    }
}

if ( ! function_exists( 'apply_filters' ) ) {
    function apply_filters( string $hook, $value, ...$args ) {
        return $value;
    }
}

// -----------------------------------------------------------------------------
// Test runner state
// -----------------------------------------------------------------------------

$tests_run    = 0;
$tests_passed = 0;
$tests_failed = 0;
$failures     = array();

function assert_eq( $expected, $actual, string $message ): void {
    global $tests_run, $tests_passed, $tests_failed, $failures;
    $tests_run++;
    if ( $expected === $actual ) {
        $tests_passed++;
        echo "  PASS: $message\n";
    } else {
        $tests_failed++;
        $exp_repr = var_export( $expected, true );
        $act_repr = var_export( $actual, true );
        $failures[] = "$message\n    expected: $exp_repr\n    actual:   $act_repr";
        echo "  FAIL: $message\n    expected: $exp_repr\n    actual:   $act_repr\n";
    }
}

function assert_no_throw( callable $fn, string $message ): void {
    global $tests_run, $tests_passed, $tests_failed, $failures;
    $tests_run++;
    try {
        $fn();
        $tests_passed++;
        echo "  PASS: $message\n";
    } catch ( \Throwable $e ) {
        $tests_failed++;
        $failures[] = "$message\n    threw: " . get_class( $e ) . ': ' . $e->getMessage();
        echo "  FAIL: $message\n    threw: " . get_class( $e ) . ': ' . $e->getMessage() . "\n";
    }
}

// -----------------------------------------------------------------------------
// F1: labels の各値を is_scalar() で正規化するロジックの単体テスト
// (実コード class-shortcode.php:222-230 と等価のロジック断片を再現)
// -----------------------------------------------------------------------------

echo "\n=== F1: labels の非 scalar 値が TypeError を起こさないこと ===\n";

function normalize_labels( string $labels_json ): array {
    $decoded = json_decode( $labels_json, true );
    $labels  = is_array( $decoded ) ? $decoded : array();
    foreach ( $labels as $key => $value ) {
        $labels[ $key ] = is_scalar( $value ) ? (string) $value : '';
    }
    return $labels;
}

// 正常ケース (string 値)
$normal = normalize_labels( '{"person-1":"執筆者","corp-2":"運営会社"}' );
assert_eq( '執筆者', $normal['person-1'], 'string value preserved' );
assert_eq( '運営会社', $normal['corp-2'], 'string value preserved' );

// 数値 (scalar) は文字列化される
$numeric = normalize_labels( '{"person-1":123,"corp-2":4.5,"org-3":true}' );
assert_eq( '123', $numeric['person-1'], 'int value coerced to string' );
assert_eq( '4.5', $numeric['corp-2'], 'float value coerced to string' );
assert_eq( '1', $numeric['org-3'], 'bool true value coerced to string' );

// 配列値が混入 → 空文字に正規化される (TypeError 回避)
$arr_label = normalize_labels( '{"person-1":["a","b"],"corp-2":"運営会社"}' );
assert_eq( '', $arr_label['person-1'], 'array value normalized to empty string' );
assert_eq( '運営会社', $arr_label['corp-2'], 'sibling string value still preserved' );

// オブジェクト値が混入 → 空文字に正規化される
$obj_label = normalize_labels( '{"person-1":{"nested":"x"},"corp-2":"運営会社"}' );
assert_eq( '', $obj_label['person-1'], 'object value normalized to empty string' );

// null 値 → 空文字に正規化される
$null_label = normalize_labels( '{"person-1":null,"corp-2":"運営会社"}' );
assert_eq( '', $null_label['person-1'], 'null value normalized to empty string' );

// esc_html() 相当の関数呼び出しで TypeError が出ないことを確認
assert_no_throw(
    function () use ( $arr_label ) {
        foreach ( $arr_label as $key => $value ) {
            // PHP 8 系では非 scalar を文字列引数として渡すと TypeError
            $escaped = htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' );
        }
    },
    'esc_html (htmlspecialchars) does not throw on normalized labels'
);

// 完全に壊れた JSON → 空配列 (既存挙動)
$broken = normalize_labels( '{not valid json' );
assert_eq( array(), $broken, 'invalid JSON returns empty array' );

// 配列でない top-level (e.g. string) → 空配列
$nontop = normalize_labels( '"just a string"' );
assert_eq( array(), $nontop, 'non-array top level returns empty array' );

// -----------------------------------------------------------------------------
// F6: extract_valid_urls の scheme 許可リスト
// (実コード class-shortcode.php:512-538 と等価のロジック断片を再現)
// -----------------------------------------------------------------------------

echo "\n=== F6: sameAs スキーム許可リスト ===\n";

function extract_valid_urls( string $text ): array {
    if ( trim( $text ) === '' ) {
        return array();
    }
    $allowed_schemes = (array) apply_filters( 'kapm_same_as_protocols', array( 'http', 'https' ) );
    $allowed_schemes = array_map( 'strtolower', $allowed_schemes );
    $lines           = array_filter( array_map( 'trim', explode( "\n", $text ) ) );
    $urls            = array();
    foreach ( $lines as $line ) {
        if ( ! filter_var( $line, FILTER_VALIDATE_URL ) ) {
            continue;
        }
        $scheme = strtolower( (string) wp_parse_url( $line, PHP_URL_SCHEME ) );
        if ( $scheme === '' || ! in_array( $scheme, $allowed_schemes, true ) ) {
            continue;
        }
        $urls[] = $line;
    }
    return array_values( array_unique( $urls ) );
}

// http / https は通る
$ok = extract_valid_urls( "https://example.com\nhttp://example.org" );
assert_eq( array( 'https://example.com', 'http://example.org' ), $ok, 'http and https allowed' );

// javascript:// は filter_var 的には valid だが scheme チェックで弾かれる
$js = extract_valid_urls( "javascript://alert(1)" );
assert_eq( array(), $js, 'javascript:// scheme blocked' );

// data: は filter_var で invalid なので素通り削除
$data = extract_valid_urls( "data:text/html,<script>alert(1)</script>" );
assert_eq( array(), $data, 'data: URL blocked' );

// file:// は scheme チェックで弾かれる
$file = extract_valid_urls( "file:///etc/passwd" );
assert_eq( array(), $file, 'file:// scheme blocked' );

// ftp:// は scheme チェックで弾かれる
$ftp = extract_valid_urls( "ftp://example.com/file.txt" );
assert_eq( array(), $ftp, 'ftp:// scheme blocked' );

// chrome:// は scheme チェックで弾かれる
$chrome = extract_valid_urls( "chrome://settings" );
assert_eq( array(), $chrome, 'chrome:// scheme blocked' );

// mailto: は scheme チェックで弾かれる
$mailto = extract_valid_urls( "mailto:a@example.com" );
assert_eq( array(), $mailto, 'mailto: scheme blocked' );

// 混在ケース: 安全な URL は残り、危険なものだけ弾かれる
$mixed = extract_valid_urls( "https://x.test/safe\njavascript://alert(1)\nhttp://y.test/safe2\nfile:///etc/passwd" );
assert_eq(
    array( 'https://x.test/safe', 'http://y.test/safe2' ),
    $mixed,
    'mixed input retains safe URLs only'
);

// 大文字スキームも正規化される
$upper = extract_valid_urls( "HTTPS://example.com" );
assert_eq( array( 'HTTPS://example.com' ), $upper, 'uppercase HTTPS scheme normalized and allowed' );

// 重複は除去される
$dup = extract_valid_urls( "https://example.com\nhttps://example.com" );
assert_eq( array( 'https://example.com' ), $dup, 'duplicate URLs removed' );

// 空テキスト
$empty = extract_valid_urls( "" );
assert_eq( array(), $empty, 'empty input returns empty array' );

// -----------------------------------------------------------------------------
// Summary
// -----------------------------------------------------------------------------

echo "\n========================================\n";
echo "Tests run:    $tests_run\n";
echo "Tests passed: $tests_passed\n";
echo "Tests failed: $tests_failed\n";

if ( $tests_failed > 0 ) {
    echo "\nFAILURES:\n";
    foreach ( $failures as $f ) {
        echo "  - $f\n";
    }
    exit( 1 );
}

echo "\n✅ All assertions passed.\n";
exit( 0 );
