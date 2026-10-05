(function () {
    var el = wp.element.createElement;
    var useState = wp.element.useState;
    var useEffect = wp.element.useEffect;
    var registerBlockType = wp.blocks.registerBlockType;
    var InspectorControls = wp.blockEditor.InspectorControls;
    var PanelBody = wp.components.PanelBody;
    var RadioControl = wp.components.RadioControl;
    var TextControl = wp.components.TextControl;
    var CheckboxControl = wp.components.CheckboxControl;
    var SelectControl = wp.components.SelectControl;
    var Button = wp.components.Button;
    var Notice = wp.components.Notice;
    var Placeholder = wp.components.Placeholder;
    var ExternalLink = wp.components.ExternalLink;

    registerBlockType('kapm/author-panel', {
        title: 'Kashiwazaki SEO Author Panel Manager',
        description: '\u8457\u8005\u30d1\u30cd\u30eb\u3092\u8868\u793a\u3057\u3001Schema.org JSON-LD\u3092\u51fa\u529b\u3057\u307e\u3059\u3002',
        icon: 'id-alt',
        category: 'widgets',
        keywords: ['author', 'panel', 'schema', '\u8457\u8005', 'kashiwazaki'],
        attributes: {
            persons: { type: 'string', default: '' },
            corporations: { type: 'string', default: '' },
            organizations: { type: 'string', default: '' },
            mode: { type: 'string', default: 'standard' },
            targetSchemaId: { type: 'string', default: '' },
            labels: { type: 'string', default: '{}' },
            imageStyles: { type: 'string', default: '{}' },
            order: { type: 'string', default: '' }
        },

        edit: function (props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;

            var _persons = useState([]);
            var persons = _persons[0];
            var setPersons = _persons[1];
            var _corps = useState([]);
            var corporations = _corps[0];
            var setCorporations = _corps[1];
            var _orgs = useState([]);
            var organizations = _orgs[0];
            var setOrganizations = _orgs[1];
            var _drag = useState(null);
            var dragKey = _drag[0];
            var setDragKey = _drag[1];

            useEffect(function () {
                var headers = { 'X-WP-Nonce': kapmData.nonce };
                fetch(kapmData.restUrl + 'persons', { headers: headers }).then(function (r) { return r.json(); }).then(setPersons);
                fetch(kapmData.restUrl + 'corporations', { headers: headers }).then(function (r) { return r.json(); }).then(setCorporations);
                fetch(kapmData.restUrl + 'organizations', { headers: headers }).then(function (r) { return r.json(); }).then(setOrganizations);
            }, []);

            function getSelectedIds(str) {
                if (!str) return [];
                return str.split(',').filter(function (s) { return s; });
            }

            function toggleId(attr, id) {
                var ids = getSelectedIds(attributes[attr]);
                var idStr = String(id);
                var idx = ids.indexOf(idStr);
                if (idx !== -1) {
                    ids.splice(idx, 1);
                } else {
                    ids.push(idStr);
                }
                var obj = {};
                obj[attr] = ids.join(',');
                setAttributes(obj);
            }

            // 壊れた labels / imageStyles JSON でエディタ全体が停止しないよう安全にパース
            function safeParseObject(str) {
                try {
                    var parsed = JSON.parse(str || '{}');
                    return (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) ? parsed : {};
                } catch (e) {
                    return {};
                }
            }
            var labelsObj = safeParseObject(attributes.labels);
            var imageStylesObj = safeParseObject(attributes.imageStyles);

            function getLabel(key) { return labelsObj[key] || ''; }
            function setLabel(key, val) {
                var obj = safeParseObject(attributes.labels);
                if (val) { obj[key] = val; } else { delete obj[key]; }
                setAttributes({ labels: JSON.stringify(obj) });
            }

            // 画像のデザイン（値はサーバー側 KAPM_Shortcode::IMAGE_STYLES と一致させる）
            var imageStyleOptions = [
                { label: '\u4e38\uff08\u6a19\u6e96\uff09', value: 'circle' },
                { label: '\u89d2\u4e38\u306e\u56db\u89d2', value: 'rounded' },
                { label: '\u56db\u89d2', value: 'square' },
                { label: '\u6955\u5186\uff08\u7e26\u9577\uff09', value: 'ellipse-v' },
                { label: '\u6955\u5186\uff08\u6a2a\u9577\uff09', value: 'ellipse-h' },
                { label: '\u5207\u308a\u629c\u304b\u306a\u3044\uff08\u5143\u306e\u5f62\u306e\u307e\u307e\uff09', value: 'original' }
            ];
            function getImageStyle(key) { return imageStylesObj[key] || 'circle'; }
            function setImageStyle(key, val) {
                var obj = safeParseObject(attributes.imageStyles);
                // 既定の丸は保存しない（属性を増やさず、既存ブロックと同じ形のまま）
                if (val && val !== 'circle') { obj[key] = val; } else { delete obj[key]; }
                setAttributes({ imageStyles: JSON.stringify(obj) });
            }

            var hasSelection = attributes.persons || attributes.corporations || attributes.organizations;

            // 並び順: キーは labels と同じ person-1 / corp-1 / org-1。値はサーバー側 KAPM_Shortcode::parse_order() で検証
            var entityTypes = [
                { prefix: 'person', attr: 'persons', list: persons, mark: '\ud83d\udc64 ' },
                { prefix: 'corp', attr: 'corporations', list: corporations, mark: '\ud83c\udfe2 ' },
                { prefix: 'org', attr: 'organizations', list: organizations, mark: '\ud83c\udfdb ' }
            ];
            function getOrderedKeys() {
                var selected = [];
                entityTypes.forEach(function (t) {
                    getSelectedIds(attributes[t.attr]).forEach(function (id) { selected.push(t.prefix + '-' + id); });
                });
                var ordered = getSelectedIds(attributes.order).filter(function (k) { return selected.indexOf(k) !== -1; });
                selected.forEach(function (k) { if (ordered.indexOf(k) === -1) { ordered.push(k); } });
                return ordered;
            }
            function getKeyLabel(key) {
                var sep = key.lastIndexOf('-');
                var prefix = key.slice(0, sep);
                var id = key.slice(sep + 1);
                var t = entityTypes.filter(function (x) { return x.prefix === prefix; })[0];
                var found = t ? t.list.filter(function (item) { return String(item.id) === id; })[0] : null;
                return (t ? t.mark : '') + (found ? found.name : 'ID:' + id);
            }
            function moveKey(fromKey, toIndex) {
                var keys = getOrderedKeys();
                var from = keys.indexOf(fromKey);
                if (from === -1 || toIndex < 0 || toIndex >= keys.length || from === toIndex) { return; }
                keys.splice(from, 1);
                keys.splice(toIndex, 0, fromKey);
                setAttributes({ order: keys.join(',') });
            }

            // Custom なのに紐付け先が空だと、構造化データが何も出力されない（サーバー側 collect_custom_data で破棄）
            var customTargetMissing = attributes.mode === 'custom' && !(attributes.targetSchemaId || '').trim();
            var customTargetMissingText = '\u7d10\u4ed8\u3051\u5148\u30b9\u30ad\u30fc\u30de\u306e@id\u304c\u7a7a\u306e\u305f\u3081\u3001\u69cb\u9020\u5316\u30c7\u30fc\u30bf\u306f\u51fa\u529b\u3055\u308c\u307e\u305b\u3093\u3002@id\u3092\u5165\u529b\u3059\u308b\u304b\u3001\u69cb\u9020\u5316\u30c7\u30fc\u30bf\u304c\u4e0d\u8981\u306a\u3089\u300c\u51fa\u529b\u3057\u306a\u3044\u300d\u3092\u9078\u3093\u3067\u304f\u3060\u3055\u3044\u3002';

            var orderedKeys = getOrderedKeys();
            var previewItems = orderedKeys.map(getKeyLabel);

            function renderOrderPanel() {
                if (orderedKeys.length < 2) { return null; }
                var rows = orderedKeys.map(function (key, i) {
                    return el('div', {
                        key: 'order-' + key,
                        draggable: true,
                        onDragStart: function (e) { e.dataTransfer.setData('text/plain', key); e.dataTransfer.effectAllowed = 'move'; setDragKey(key); },
                        onDragOver: function (e) { e.preventDefault(); },
                        onDrop: function (e) { e.preventDefault(); moveKey(e.dataTransfer.getData('text/plain'), i); setDragKey(null); },
                        onDragEnd: function () { setDragKey(null); },
                        style: {
                            display: 'flex', alignItems: 'center', gap: '4px',
                            padding: '4px 4px 4px 8px', marginBottom: '4px',
                            border: '1px solid #ddd', borderRadius: '4px',
                            background: dragKey === key ? '#f0f6fc' : '#fff', cursor: 'grab'
                        }
                    },
                        el('span', { className: 'dashicons dashicons-menu', 'aria-hidden': 'true', style: { color: '#757575' } }),
                        el('span', { style: { flex: 1, fontSize: '13px' } }, getKeyLabel(key)),
                        el(Button, { icon: 'arrow-up-alt2', label: '\u4e0a\u3078', size: 'small', disabled: i === 0, accessibleWhenDisabled: true, onClick: function () { moveKey(key, i - 1); } }),
                        el(Button, { icon: 'arrow-down-alt2', label: '\u4e0b\u3078', size: 'small', disabled: i === orderedKeys.length - 1, accessibleWhenDisabled: true, onClick: function () { moveKey(key, i + 1); } })
                    );
                });
                return el(PanelBody, { title: '\u4e26\u3073\u9806', initialOpen: true },
                    el('p', { style: { fontSize: '12px', color: '#757575', marginTop: 0 } },
                        '\u30c9\u30e9\u30c3\u30b0\u304b \u2191\u2193 \u3067\u3001\u30d1\u30cd\u30eb\u3092\u8868\u793a\u3059\u308b\u9806\u756a\u3092\u5909\u3048\u3089\u308c\u307e\u3059\u3002'),
                    rows
                );
            }

            function renderEntityPanel(title, list, attrKey, prefix, tabName, placeholder) {
                var selectedIds = getSelectedIds(attributes[attrKey]);
                var items = [];
                if (list.length === 0) {
                    items.push(el('p', { key: 'empty' }, title + '\u304c\u767b\u9332\u3055\u308c\u3066\u3044\u307e\u305b\u3093\u3002'));
                } else {
                    list.forEach(function (item) {
                        var isChecked = selectedIds.indexOf(String(item.id)) !== -1;
                        items.push(el(CheckboxControl, {
                            key: prefix + '-' + item.id,
                            label: item.name + (item.name_en ? ' (' + item.name_en + ')' : '') + ' [' + item.role + ']',
                            checked: isChecked,
                            onChange: function () { toggleId(attrKey, item.id); }
                        }));
                        if (isChecked) {
                            var lkey = prefix + '-' + item.id;
                            items.push(el(TextControl, {
                                key: 'label-' + lkey,
                                label: item.name + ' \u306e\u8868\u793a\u30e9\u30d9\u30eb',
                                value: getLabel(lkey),
                                onChange: function (val) { setLabel(lkey, val); },
                                placeholder: placeholder
                            }));
                            items.push(el(SelectControl, {
                                key: 'image-style-' + lkey,
                                label: item.name + ' \u306e\u753b\u50cf\u306e\u30c7\u30b6\u30a4\u30f3',
                                value: getImageStyle(lkey),
                                options: imageStyleOptions,
                                onChange: function (val) { setImageStyle(lkey, val); }
                            }));
                        }
                    });
                }
                items.push(el('div', { key: 'link', style: { marginTop: '8px', fontSize: '12px' } },
                    el(ExternalLink, { href: kapmData.adminUrl + '&tab=' + tabName }, title + '\u7ba1\u7406\u3092\u958b\u304f')));
                return el(PanelBody, { title: title, initialOpen: true }, items);
            }

            return el('div', {},
                el(InspectorControls, {},
                    renderEntityPanel('Person', persons, 'persons', 'person', 'person', '\u4f8b: \u57f7\u7b46\u8005\u3001\u76e3\u4fee\u8005\u306a\u3069'),
                    renderEntityPanel('Corporation', corporations, 'corporations', 'corp', 'corporation', '\u4f8b: \u904b\u55b6\u4f1a\u793e\u3001\u30b9\u30dd\u30f3\u30b5\u30fc\u306a\u3069'),
                    renderEntityPanel('Organization', organizations, 'organizations', 'org', 'organization', '\u4f8b: \u904b\u55b6\u30e1\u30c7\u30a3\u30a2\u3001\u767a\u884c\u5143\u306a\u3069'),
                    renderOrderPanel(),
                    el(PanelBody, { title: '\u51fa\u529b\u30e2\u30fc\u30c9', initialOpen: true },
                        el(RadioControl, {
                            selected: attributes.mode,
                            options: [
                                { label: 'Standard\uff08\u901a\u5e38\uff09', value: 'standard' },
                                { label: 'Custom\uff08\u4ed6\u30d7\u30e9\u30b0\u30a4\u30f3\u306e\u30b9\u30ad\u30fc\u30de\u306b\u7d10\u4ed8\u3051\uff09', value: 'custom' },
                                { label: '\u51fa\u529b\u3057\u306a\u3044\uff08\u30d1\u30cd\u30eb\u306e\u8868\u793a\u3060\u3051\uff09', value: 'none' }
                            ],
                            onChange: function (val) { setAttributes({ mode: val }); }
                        }),
                        attributes.mode === 'standard'
                            ? el('p', { style: { fontSize: '12px', color: '#757575' } },
                                '\u8457\u8005\u30d1\u30cd\u30ebHTML\u3068\u72ec\u7acb\u3057\u305fJSON-LD\u3092\u51fa\u529b\u3057\u307e\u3059\u3002')
                            : null,
                        attributes.mode === 'custom'
                            ? el(TextControl, {
                                label: '\u7d10\u4ed8\u3051\u5148\u30b9\u30ad\u30fc\u30de\u306e@id',
                                help: '\u4ed6\u30d7\u30e9\u30b0\u30a4\u30f3\u304c\u51fa\u529b\u3057\u3066\u3044\u308bJSON-LD\u5185\u306e@id\u5024\u3092\u6307\u5b9a',
                                value: attributes.targetSchemaId,
                                onChange: function (val) { setAttributes({ targetSchemaId: val }); },
                                placeholder: 'https://example.com/#article'
                            })
                            : null,
                        customTargetMissing
                            ? el(Notice, { status: 'warning', isDismissible: false }, customTargetMissingText)
                            : null,
                        attributes.mode === 'none'
                            ? el('p', { style: { fontSize: '12px', color: '#757575' } },
                                '\u69cb\u9020\u5316\u30c7\u30fc\u30bf\uff08JSON-LD\uff09\u306f\u51fa\u529b\u3057\u307e\u305b\u3093\u3002\u30d1\u30cd\u30eb\u3060\u3051\u3092\u8868\u793a\u3057\u307e\u3059\u3002')
                            : null
                    )
                ),
                hasSelection
                    ? el('div', { style: { padding: '16px 20px', background: '#f9f9f9', border: '1px solid #e0e0e0', borderRadius: '6px' } },
                        el('div', { style: { fontWeight: 'bold', marginBottom: '8px', fontSize: '14px' } }, 'Kashiwazaki SEO Author Panel Manager'),
                        el('div', { style: { fontSize: '13px', color: '#555' } },
                            previewItems.map(function(item, i) {
                                return el('div', { key: i, style: { marginBottom: '2px' } }, item);
                            })
                        ),
                        el('div', { style: { fontSize: '11px', color: '#999', marginTop: '8px' } },
                            'Mode: ' + attributes.mode + (attributes.mode === 'custom' && attributes.targetSchemaId ? ' | Target: ' + attributes.targetSchemaId : '')),
                        customTargetMissing
                            ? el('div', { style: { fontSize: '12px', color: '#b32d2e', marginTop: '6px' } }, '\u26a0 ' + customTargetMissingText)
                            : null
                    )
                    : el(Placeholder, {
                        icon: 'id-alt',
                        label: 'Kashiwazaki SEO Author Panel Manager',
                        instructions: '\u53f3\u30b5\u30a4\u30c9\u30d0\u30fc\u306ePerson / Corporation / Organization\u304b\u3089\u8868\u793a\u3059\u308b\u30a8\u30f3\u30c6\u30a3\u30c6\u30a3\u3092\u9078\u629e\u3057\u3066\u304f\u3060\u3055\u3044\u3002'
                    })
            );
        },

        save: function () {
            return null;
        }
    });
})();
