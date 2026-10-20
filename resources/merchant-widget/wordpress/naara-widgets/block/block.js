/* Gutenberg block `naara/widget`: no build step, server-side rendered. It only chooses a product type and theme. */
(function (blocks, element, blockEditor, components) {
  var el = element.createElement;
  var cfg = window.naaraWidgets || { configured: true, settingsUrl: '#' };
  blocks.registerBlockType('naara/widget', {
    apiVersion: 2,
    title: 'Naara widget',
    icon: 'smartphone',
    category: 'widgets',
    attributes: { type: { type: 'string', default: 'esim' }, theme: { type: 'string', default: 'auto' } },
    edit: function (props) {
      var a = props.attributes;
      return el('div', blockEditor.useBlockProps({ style: { padding: '18px', border: '0', borderRadius: '22px', background: 'linear-gradient(145deg,#fff,#eef3f7)', boxShadow: '8px 10px 20px -10px rgba(10,36,49,.28),-6px -6px 14px #fff' } }),
        el(blockEditor.InspectorControls, null,
          el(components.PanelBody, { title: 'Naara widget', initialOpen: true },
            el(components.SelectControl, { label: 'Product', value: a.type, options: [{ label: 'eSIM data plans', value: 'esim' }, { label: 'Verification numbers', value: 'number' }, { label: 'Gift cards', value: 'giftcard' }, { label: 'Everything (with tabs)', value: '' }, { label: 'My orders page (customer sign-in)', value: 'account' }], onChange: function (v) { props.setAttributes({ type: v }); } }),
            el(components.SelectControl, { label: 'Theme', value: a.theme, options: [{ label: 'Match visitor', value: 'auto' }, { label: 'Light', value: 'light' }, { label: 'Dark', value: 'dark' }], onChange: function (v) { props.setAttributes({ theme: v }); } }))),
        el('strong', null, 'Naara widget'), el('div', null, 'Shows ' + ({ giftcard: 'gift cards', esim: 'eSIM data plans', number: 'verification numbers', account: 'the customer My orders page' }[a.type] || 'eSIMs, verification numbers and gift cards') + ' on your published page.'),
        cfg.configured
          ? el('div', { style: { marginTop: '8px', color: '#067647', fontWeight: 600 } }, 'Connected to Naara. Preview the page to see it live.')
          : el('div', { style: { marginTop: '8px', color: '#b42318', fontWeight: 600 } }, 'Not connected yet. ', el('a', { href: cfg.settingsUrl }, 'Add your widget key')));
    },
    save: function () { return null; }
  });
})(window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components);
