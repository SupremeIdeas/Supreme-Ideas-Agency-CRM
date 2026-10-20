const hook = require('./_restHook');
module.exports = {
  key: 'new_call', noun: 'Call',
  display: { label: 'Call Completed', description: 'Triggers when a call on one of your workspace numbers is answered or missed.' },
  operation: {
    type: 'hook', ...hook('call.completed'),
    performList: async (z, bundle) => {
      const res = await z.request({ url: `${bundle.authData.base_url}/api/v1/business/calls`, params: { limit: 5 }, headers: { Authorization: `Bearer ${bundle.authData.api_key}` } });
      return res.data.data;
    },
    sample: { id: 1, call_id: 'CA123', from: '+2348012345678', to: '+15005550006', status: 'answered', at: '2026-10-05T09:00:00+00:00' },
  },
};
