/* Chat gestures settings screen (MASTER-ONLY): loads the same engine the thread uses and reports what a practice bubble would do. */
import './ctx.js';
import './gestures.js';

const LABEL = { reply: 'Reply: that is what a swipe toward the end does.', info: 'Info: the message details would open.', forward: 'Forward: the forward sheet would open.', star: 'Star: the message would be starred.', react: 'Reacted: your double-tap reaction would be added or removed.', menu: 'Menu: the actions menu would open.' };
window.addEventListener('ng-practice', (e) => {
  const out = document.getElementById('ng-practice-log'); if (!out) { return; }
  out.textContent = LABEL[e.detail && e.detail.action] || 'Done.';
  out.dataset.last = (e.detail && e.detail.action) || '';
});
