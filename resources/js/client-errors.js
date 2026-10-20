// Browser error beacon (engineering blueprint 0.3). One uncaught error inside an Alpine component silently kills its reactivity, which users
// describe as "it hangs until I refresh". This reports the real error so it can be fixed instead of guessed at. Rate-limited on both sides.
const SENT = new Set();
let budget = 8; // reports per page load

function post(kind, message, stack, component) {
    try {
        if (budget <= 0 || !message) return;
        const key = kind + '|' + message + '|' + String(stack || '').split('\n')[0];
        if (SENT.has(key)) return;
        SENT.add(key);
        budget--;
        const token = document.querySelector('meta[name=csrf-token]')?.content;
        fetch('/_diag/client-error', {
            method: 'POST',
            keepalive: true,
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', ...(token ? { 'X-CSRF-TOKEN': token } : {}) },
            body: JSON.stringify({
                kind,
                message: String(message).slice(0, 1900),
                stack: stack ? String(stack).slice(0, 7500) : null,
                route: location.pathname,
                component: component || null,
                vw: window.innerWidth,
            }),
        }).catch(() => {});
    } catch (e) { /* the reporter must never throw */ }
}

export function registerClientErrors() {
    if (window.__naaraClientErrors) return;
    window.__naaraClientErrors = true;

    window.addEventListener('error', (e) => {
        // Ignore resource load failures (images, fonts) and cross-origin "Script error." with no detail: not actionable.
        if (!e.message || e.message === 'Script error.') return;
        post('error', e.message + (e.filename ? ' @' + e.filename.split('/').pop() + ':' + e.lineno : ''), e.error && e.error.stack);
    });
    window.addEventListener('unhandledrejection', (e) => {
        const r = e.reason;
        post('rejection', (r && r.message) || String(r || 'Unhandled rejection'), r && r.stack);
    });
    document.addEventListener('livewire:init', () => {
        try {
            window.Livewire.hook('request', ({ fail }) => {
                fail(({ status, content, preventDefault }) => {
                    // 419 (expired page) is handled by Livewire's own prompt; 5xx and network errors are what we want to see.
                    if (status === 419) return;
                    post('livewire', 'Livewire request failed with status ' + status, null);
                });
            });
        } catch (e) { /* older/newer Livewire hook shape: skip */ }
    });
}

registerClientErrors();
