// Shared REST Hook plumbing: Zapier subscribes a webhook when a Zap is turned on and removes it when it is turned off, so nothing polls.
// Naara signs every delivery (X-Naara-Signature: sha256=HMAC-SHA256(raw body, secret)); the secret is returned once when the subscription is created.
module.exports = (event) => ({
  performSubscribe: async (z, bundle) => {
    const res = await z.request({
      url: `${bundle.authData.base_url}/api/v1/business/webhooks`, method: 'POST',
      headers: { Authorization: `Bearer ${bundle.authData.api_key}` },
      body: { event_type: event, target_url: bundle.targetUrl },
    });
    return res.data.data; // { id, secret, ... }, handed back to performUnsubscribe as bundle.subscribeData
  },
  performUnsubscribe: async (z, bundle) => {
    await z.request({ url: `${bundle.authData.base_url}/api/v1/business/webhooks/${bundle.subscribeData.id}`, method: 'DELETE', headers: { Authorization: `Bearer ${bundle.authData.api_key}` } });
    return {};
  },
  parsePayload: (z, bundle) => [bundle.cleanedRequest.data],
});
