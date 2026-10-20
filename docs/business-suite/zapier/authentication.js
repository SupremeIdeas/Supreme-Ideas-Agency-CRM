// API-key auth. The person pastes a Naara API key made in Developer API (scopes: messages:read, calls:read, webhook:subscribe, bound to their workspace)
// and the address of their Naara install, e.g. https://app.example.com
module.exports = {
  type: 'custom',
  fields: [
    { key: 'base_url', label: 'Naara address', required: true, helpText: 'The web address of your Naara install, for example https://app.example.com' },
    { key: 'api_key', label: 'API key', required: true, type: 'password', helpText: 'Create one in Developer API with the Business scopes and your workspace selected.' },
  ],
  // A harmless authenticated call that needs a Business scope, so a wrong key or workspace fails here.
  test: (z, bundle) => z.request({ url: `${bundle.authData.base_url}/api/v1/business/webhooks`, headers: { Authorization: `Bearer ${bundle.authData.api_key}` } }),
  connectionLabel: '{{bundle.authData.base_url}}',
};
