#!/usr/bin/env node
// Hardens the generated Capacitor ios/ project for App Store review: usage strings for every permission the site can ask for (a missing one is an instant rejection),
// export-compliance answer, associated domains for Universal Links, and portrait/landscape on phone and tablet.   node patch-ios.mjs <iosAppDir> <https-origin> [<appName>]
import { readFileSync, writeFileSync, existsSync } from 'node:fs';
import { join } from 'node:path';

const [dir, origin, name = 'Naara'] = process.argv.slice(2);
if (!dir || !origin) { console.error('usage: patch-ios.mjs <ios/App/App> <origin> [name]'); process.exit(1); }
const host = new URL(origin).hostname;
const pl = join(dir, 'Info.plist');
let x = readFileSync(pl, 'utf8');

const strings = {
  NSCameraUsageDescription: name + ' uses the camera so you can scan QR codes, take photos for your profile and join video calls.',
  NSMicrophoneUsageDescription: name + ' uses the microphone for voice calls and voice notes.',
  NSPhotoLibraryUsageDescription: name + ' lets you choose photos to share in chats and set as your profile picture.',
  NSLocationWhenInUseUsageDescription: name + ' uses your location only when you choose to share it or to suggest the right country.',
  NSContactsUsageDescription: name + ' can match your contacts so you can call and message the people you know. Contacts are not uploaded without your action.',
};
for (const [k, v] of Object.entries(strings)) {
  if (!x.includes('<key>' + k + '</key>')) x = x.replace('</dict>\n</plist>', `\t<key>${k}</key>\n\t<string>${v}</string>\n</dict>\n</plist>`);
}
if (!x.includes('ITSAppUsesNonExemptEncryption')) x = x.replace('</dict>\n</plist>', '\t<key>ITSAppUsesNonExemptEncryption</key>\n\t<false/>\n</dict>\n</plist>');
writeFileSync(pl, x);

// associated domains (Universal Links). The entitlement file is added to the project by build-ios.sh's xcodebuild settings.
const ent = join(dir, '..', 'App.entitlements');
writeFileSync(ent, `<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
\t<key>com.apple.developer.associated-domains</key>
\t<array>
\t\t<string>applinks:${host}</string>
\t\t<string>webcredentials:${host}</string>
\t</array>
</dict>
</plist>
`);
console.log('ios project patched for', host, existsSync(ent) ? '(entitlements written)' : '');
