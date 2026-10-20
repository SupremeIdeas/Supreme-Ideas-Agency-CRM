/** Global call layer (N2N, MASTER-ONLY): realtime + the call client on EVERY signed-in page, so an incoming Naara call rings wherever the member is. */
import './realtime.js';
import './call.js';

const rt = window.N2NRealtime;
function boot() {
  if (!rt) { return; }
  rt.start();
  if (!window.__n2nPing) { window.__n2nPing = setInterval(() => rt.ping(), 45000); rt.ping(); }
}
if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', boot); } else { boot(); }
