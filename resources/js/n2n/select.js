// Selection mode for Naara chat (MASTER-ONLY): pick several chats in the list, or several messages in a thread, then act on them together. Selection lives in Alpine
// (nothing is sent until an action is chosen); every action is a Livewire call that re-checks each id as the member's own. "Select all" always means "everything
// the list is showing right now", so searching or filtering first and then selecting all is how you find and delete a set of chats.
const DAY = 86400;

window.n2nSel = function n2nSel() {
  return {
    on: false, ids: [], from: '', to: '', menu: '',
    start(id) { this.on = true; this.ids = []; if (id) { this.toggle(Number(id)); } },
    stop() { this.on = false; this.ids = []; this.menu = ''; },
    rows() { return Array.from(this.$root.querySelectorAll('li[data-sel]')); },
    has(id) { return this.ids.includes(Number(id)); },
    toggle(id) { id = Number(id); this.ids = this.has(id) ? this.ids.filter((i) => i !== id) : this.ids.concat(id); },
    all() { this.ids = this.rows().map((li) => Number(li.dataset.sel)); },
    none() { this.ids = []; },
    invert() { const keep = new Set(this.ids); this.ids = this.rows().map((li) => Number(li.dataset.sel)).filter((i) => !keep.has(i)); },
    // Quick time picks: chats whose last message is older than N days.
    older(days) { const cut = Date.now() / 1000 - days * DAY; this.ids = this.rows().filter((li) => Number(li.dataset.at) < cut).map((li) => Number(li.dataset.sel)); },
    // A date range (inclusive) picked from two date inputs.
    range() {
      if (!this.from && !this.to) { return; }
      const a = this.from ? new Date(this.from + 'T00:00:00').getTime() / 1000 : 0; const b = this.to ? new Date(this.to + 'T23:59:59').getTime() / 1000 : Infinity;
      this.ids = this.rows().filter((li) => { const t = Number(li.dataset.at); return t >= a && t <= b; }).map((li) => Number(li.dataset.sel));
    },
    unreadOnly() { this.ids = this.rows().filter((li) => li.dataset.unread === '1').map((li) => Number(li.dataset.sel)); },
    get count() { return this.ids.length; },
    run(action, ask) {
      if (!this.ids.length) { return; }
      if (ask && !window.confirm(ask.replace('{n}', this.ids.length))) { return; }
      this.$wire.bulkRows(this.ids.slice(), action); this.stop();
    },
  };
};

window.n2nMsgSel = function n2nMsgSel() {
  return {
    on: false, ids: [], from: '', to: '',
    start(id) { this.on = true; this.ids = []; if (id) { this.toggle(Number(id)); } },
    stop() { this.on = false; this.ids = []; },
    bubbles() { return Array.from(this.$root.querySelectorAll('.ncb[data-ctx]')); },
    idOf(el) { try { return Number(JSON.parse(el.dataset.ctx).id); } catch (e) { return 0; } },
    has(id) { return this.ids.includes(Number(id)); },
    toggle(id) { id = Number(id); if (!id) { return; } this.ids = this.has(id) ? this.ids.filter((i) => i !== id) : this.ids.concat(id); this.paint(); },
    all() { this.ids = this.bubbles().map((b) => this.idOf(b)).filter(Boolean); this.paint(); },
    // Pick by time: a message is chosen by its own timestamp (data-at on the bubble), so a chat can be pruned by date.
    pick(fn) { this.ids = this.bubbles().filter((b) => fn(Number(b.dataset.at || 0), b)).map((b) => this.idOf(b)).filter(Boolean); this.paint(); },
    older(days) { const cut = Date.now() / 1000 - days * DAY; this.pick((t) => t > 0 && t < cut); },
    today() { const d = new Date(); d.setHours(0, 0, 0, 0); const s = d.getTime() / 1000; this.pick((t) => t >= s); },
    range() { if (!this.from && !this.to) { return; } const a = this.from ? new Date(this.from + 'T00:00:00').getTime() / 1000 : 0; const b = this.to ? new Date(this.to + 'T23:59:59').getTime() / 1000 : Infinity; this.pick((t) => t >= a && t <= b); },
    mine() { this.pick((t, b) => { try { return !!JSON.parse(b.dataset.ctx).mine; } catch (e) { return false; } }); },
    theirs() { this.pick((t, b) => { try { return !JSON.parse(b.dataset.ctx).mine; } catch (e) { return false; } }); },
    paint() { this.bubbles().forEach((b) => b.classList.toggle('is-selected', this.has(this.idOf(b)))); },
    get count() { return this.ids.length; },
    copy() { const t = this.bubbles().filter((b) => this.has(this.idOf(b))).map((b) => { try { return JSON.parse(b.dataset.ctx).body || ''; } catch (e) { return ''; } }).filter(Boolean).join('\n'); if (t && navigator.clipboard) { navigator.clipboard.writeText(t).catch(() => {}); } this.stop(); },
    star() { this.$wire.bulkStarMessages(this.ids.slice(), true); this.stop(); },
    hide() { if (!this.ids.length) { return; } if (!window.confirm(`Delete ${this.ids.length} ${this.ids.length === 1 ? 'message' : 'messages'} for you? The other person keeps them.`)) { return; } this.$wire.bulkHideMessages(this.ids.slice()); this.stop(); },
  };
};

// Taps on a bubble while selecting toggle it instead of opening anything (capture phase, so no inner handler runs).
document.addEventListener('click', (e) => {
  const th = e.target.closest && e.target.closest('.nc__thread'); if (!th || !window.Alpine) { return; }
  let data = null; try { data = window.Alpine.$data(th); } catch (err) { return; }
  if (!data || !data.on || typeof data.idOf !== 'function') { return; }
  const b = e.target.closest('.ncb[data-ctx]'); if (!b) { return; }
  e.preventDefault(); e.stopPropagation(); data.toggle(data.idOf(b));
}, true);
