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
            labels: { type: 'string', default: '{}' }
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

            var labelsObj = JSON.parse(attributes.labels || '{}');

            function getLabel(key) { return labelsObj[key] || ''; }
            function setLabel(key, val) {
                var obj = JSON.parse(attributes.labels || '{}');
                if (val) { obj[key] = val; } else { delete obj[key]; }
                setAttributes({ labels: JSON.stringify(obj) });
            }

            function getSelectedNames(ids, list) {
                if (!ids) return [];
                return ids.split(',').filter(function(s) { return s; }).map(function(id) {
                    var found = list.filter(function(item) { return String(item.id) === id; })[0];
                    return found ? found.name : 'ID:' + id;
                });
            }

            var hasSelection = attributes.persons || attributes.corporations || attributes.organizations;

            var previewItems = [];
            if (attributes.persons) {
                previewItems = previewItems.concat(getSelectedNames(attributes.persons, persons).map(function(n) { return '\ud83d\udc64 ' + n; }));
            }
            if (attributes.corporations) {
                previewItems = previewItems.concat(getSelectedNames(attributes.corporations, corporations).map(function(n) { return '\ud83c\udfe2 ' + n; }));
            }
            if (attributes.organizations) {
                previewItems = previewItems.concat(getSelectedNames(attributes.organizations, organizations).map(function(n) { return '\ud83c\udfdb ' + n; }));
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
                    el(PanelBody, { title: '\u51fa\u529b\u30e2\u30fc\u30c9', initialOpen: true },
                        el(RadioControl, {
                            selected: attributes.mode,
                            options: [
                                { label: 'Standard\uff08\u901a\u5e38\uff09', value: 'standard' },
                                { label: 'Custom\uff08\u4ed6\u30d7\u30e9\u30b0\u30a4\u30f3\u306e\u30b9\u30ad\u30fc\u30de\u306b\u7d10\u4ed8\u3051\uff09', value: 'custom' }
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
                            'Mode: ' + attributes.mode + (attributes.mode === 'custom' && attributes.targetSchemaId ? ' | Target: ' + attributes.targetSchemaId : ''))
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
