const authentication = require('./authentication');
const newMessage = require('./triggers/new_message');
const newCall = require('./triggers/new_call');
const recordingReady = require('./triggers/recording_ready');

module.exports = {
  version: require('./package.json').version,
  platformVersion: require('zapier-platform-core').version,
  authentication,
  beforeRequest: [(request) => { request.headers = { ...request.headers, Accept: 'application/json', 'Content-Type': 'application/json' }; return request; }],
  triggers: { [newMessage.key]: newMessage, [newCall.key]: newCall, [recordingReady.key]: recordingReady },
};
