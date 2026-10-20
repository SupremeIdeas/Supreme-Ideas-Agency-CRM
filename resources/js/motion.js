// Dashboard page transitions (PageMotion, MASTER-ONLY). Livewire's wire:navigate swaps the page body without a real navigation, so the browser's cross-document
// View Transitions never fire and Livewire does not wrap its swap in document.startViewTransition. The lightest reliable alternative is to hang two CSS states
// off Livewire's own events: `livewire:navigate` (a link was tapped: dim the content), `livewire:navigated` (the new page is in: animate it in). The CSS only
// touches opacity/transform on [data-nx-page]; the header and bottom bar are outside it and never move. Nothing runs unless the admin switch put
// data-nx-pt on <html>, and nothing runs for reduced-motion users.
(function () {
  if (window.__nxPageTransition) { return; }
  window.__nxPageTransition = true;
  const html = document.documentElement;
  const reduced = window.matchMedia ? window.matchMedia('(prefers-reduced-motion: reduce)') : { matches: false };
  const frugal = () => { const c = navigator.connection; return !!(c && (c.saveData || /(^|-)2g$/.test(c.effectiveType || ''))) || (navigator.deviceMemory && navigator.deviceMemory <= 1); };
  const on = () => html.hasAttribute('data-nx-pt') && !reduced.matches && html.getAttribute('data-nx-motion') !== 'reduced';   // the member's own motion dial wins
  let timer = 0;
  const clear = () => { html.classList.remove('nx-leave', 'nx-enter'); clearTimeout(timer); };
  document.addEventListener('livewire:navigate', () => { if (!on()) { return; } clear(); html.classList.add('nx-leave'); timer = setTimeout(clear, 4000); });   // a failed navigation never leaves the page dimmed
  document.addEventListener('livewire:navigated', () => {
    clear(); if (!on()) { return; }
    // On very constrained devices the fluid style quietly falls back to the plain fade.
    if (frugal() && html.getAttribute('data-nx-pt') === 'fluid') { html.setAttribute('data-nx-pt', 'fade'); }
    html.classList.add('nx-enter');
    timer = setTimeout(clear, 1300);   // also frees will-change once the animation is done
  });
})();
