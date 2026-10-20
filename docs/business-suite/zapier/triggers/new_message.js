const hook = require('./_restHook');
module.exports = {
  key: 'new_message', noun: 'Message',
  display: { label: 'New Message', description: 'Triggers when a customer messages your workspace on SMS, WhatsApp, Telegram or Instagram.' },
  operation: {
    type: 'hook', ...hook('message.received'),
    // Sample data (and a polling fallback while building the Zap) from the read-only history endpoint.
    performList: async (z, bundle) => {
      const res = await z.request({ url: `${bundle.authData.base_url}/api/v1/business/messages`, params: { limit: 5 }, headers: { Authorization: `Bearer ${bundle.authData.api_key}` } });
      return res.data.data.filter((m) => m.direction === 'in').map((m) => ({ ...m, id: m.id }));
    },
    sample: { id: 'in_1', direction: 'in', channel: 'whatsapp', counterpart: '2348012345678', body: 'Hello, is the plan available?', at: '2026-10-05T09:00:00+00:00' },
  },
};
