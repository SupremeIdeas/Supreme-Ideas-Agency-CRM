const hook = require('./_restHook');
module.exports = {
  key: 'recording_ready', noun: 'Recording',
  display: { label: 'Call Recording Ready', description: 'Triggers when a call recording has been saved (metadata only: no audio or transcript text is sent).' },
  operation: { type: 'hook', ...hook('call.recording_ready'), performList: async () => [], sample: { recording_id: 7, call_id: 'CA123', duration_seconds: 94, source: 'recording' } },
};
