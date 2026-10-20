// Dedicated /faq page (owner handoff, 2026-09-21): topic switch, live search
// with highlighting, solo-open accordion, mobile scrubber reveal, back-to-top.
// Mounted per `root` (the `[data-faq-page]` wrapper) so a Livewire SPA
// re-navigation to /faq gets a fresh, non-duplicated set of listeners —
// same destroyer contract as timed-tab-switcher.js.
export function mountFaqPage(root) {
    const searchInput = root.querySelector('[data-faq-search]');
    const allItems = root.querySelectorAll('.ef-faq-item');
    const emptyState = root.querySelector('[data-faq-empty]');
    const sections = root.querySelectorAll('.ef-section');
    const navLinks = root.querySelectorAll('.ef-sidebar-link, .scrub-node');
    const scrubber = root.querySelector('.ef-scrubber');
    const faqRoot = root.querySelector('[data-faq-root]');
    const ctaSection = root.querySelector('[data-faq-cta]');
    const bttBtn = root.querySelector('[data-faq-btt]');

    allItems.forEach((item) => {
        const q = item.querySelector('.ef-faq-q');
        const a = item.querySelector('.ef-faq-answer');
        if (q) q.dataset.orig = q.innerHTML;
        if (a) a.dataset.orig = a.innerHTML;
    });

    function escRx(s) { return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); }
    // Highlights `term` only inside text segments, never inside a tag's own
    // markup (e.g. an href) — answers carry real <a>/<strong> tags, and a
    // term like "refund" legitimately appears inside href="/refund-policy"
    // too, which a blind whole-string replace would corrupt.
    function highlight(html, term) {
        if (!term) return html;
        const rx = new RegExp(`(${escRx(term)})`, 'gi');
        return html.split(/(<[^>]*>)/g)
            .map((segment) => (segment.startsWith('<') ? segment : segment.replace(rx, '<mark class="ef-mark">$1</mark>')))
            .join('');
    }

    function switchSection(targetId, scroll) {
        if (!targetId) return;
        sections.forEach((s) => { s.classList.remove('active-section'); s.style.display = 'none'; });
        const target = root.querySelector(`#${CSS.escape(targetId)}`);
        if (target) {
            target.style.display = 'block';
            void target.offsetWidth;
            target.classList.add('active-section');
            if (scroll) {
                const layout = root.querySelector('.ef-layout');
                if (layout) {
                    const top = layout.getBoundingClientRect().top + window.pageYOffset - 100;
                    if (window.scrollY > top) window.scrollTo({ top, behavior: 'smooth' });
                }
            }
        }
        navLinks.forEach((link) => link.classList.toggle('active', link.dataset.target === targetId));
    }

    const onNavClick = (e) => {
        const link = e.target.closest('.ef-sidebar-link, .scrub-node');
        if (!link) return;
        e.preventDefault();
        if (searchInput && searchInput.value) {
            searchInput.value = '';
            searchInput.dispatchEvent(new Event('input'));
        }
        switchSection(link.dataset.target, true);
    };
    root.addEventListener('click', onNavClick);

    if (sections.length > 0) switchSection(sections[0].id, false);

    const onSearchInput = (e) => {
        const term = e.target.value.trim().toLowerCase();
        let count = 0;

        if (term.length > 0) {
            sections.forEach((s) => { s.classList.remove('active-section'); s.style.display = 'none'; });
        } else {
            const active = root.querySelector('.ef-sidebar-link.active');
            if (active) switchSection(active.dataset.target, false);
        }

        allItems.forEach((item) => {
            const q = item.querySelector('.ef-faq-q');
            const a = item.querySelector('.ef-faq-answer');
            if (!q || !a) return;
            const searchable = (q.dataset.orig + ' ' + a.dataset.orig).toLowerCase();

            if (searchable.includes(term)) {
                item.style.display = '';
                if (term) {
                    q.innerHTML = highlight(q.dataset.orig, term);
                    a.innerHTML = highlight(a.dataset.orig, term);
                    const parent = item.closest('.ef-section');
                    if (parent) { parent.style.display = 'block'; parent.classList.add('active-section'); }
                } else {
                    q.innerHTML = q.dataset.orig;
                    a.innerHTML = a.dataset.orig;
                }
                if (term.length > 3) item.setAttribute('open', true);
                count++;
            } else {
                item.style.display = 'none';
            }
        });

        if (emptyState) emptyState.style.display = count === 0 ? 'block' : 'none';
    };
    if (searchInput) searchInput.addEventListener('input', onSearchInput);

    const toggleHandlers = [];
    allItems.forEach((item) => {
        const onToggle = () => {
            if (item.open) {
                allItems.forEach((other) => { if (other !== item && other.open) other.removeAttribute('open'); });
                setTimeout(() => {
                    const rect = item.getBoundingClientRect();
                    if (rect.top < 110 || rect.bottom > window.innerHeight) {
                        window.scrollTo({ top: rect.top + window.pageYOffset - 130, behavior: 'smooth' });
                    }
                }, 55);
            }
        };
        item.addEventListener('toggle', onToggle);
        toggleHandlers.push([item, onToggle]);
    });

    let onScroll = null;
    let onBttClick = null;
    if (bttBtn) {
        onScroll = () => bttBtn.classList.toggle('show', window.scrollY > 700);
        onBttClick = () => window.scrollTo({ top: 0, behavior: 'smooth' });
        window.addEventListener('scroll', onScroll);
        bttBtn.addEventListener('click', onBttClick);
    }

    // Visible only in the window between the hero and the CTA: hidden
    // through the hero (guarded on scroll position too, since a short
    // viewport can already share its first screen with the top of
    // `faqRoot` — the observer alone would reveal the scrubber over the
    // hero before the visitor has scrolled at all), reappears if the
    // visitor scrolls back up out of the CTA into the topics area, and
    // hides again once the CTA takes over. Both observers only fire on
    // enter/exit, so a plain scroll listener re-checks the combined
    // condition continuously while a boundary is already crossed.
    let scrubberObserver = null;
    let ctaObserver = null;
    let onScrubberScroll = null;
    if (scrubber && faqRoot) {
        let inFaqArea = false;
        let inCta = false;
        const applyScrubberVisibility = () => scrubber.classList.toggle('is-visible', inFaqArea && !inCta && window.scrollY > 200);

        scrubberObserver = new IntersectionObserver((entries) => {
            entries.forEach((entry) => { inFaqArea = entry.isIntersecting; });
            applyScrubberVisibility();
        }, { root: null, threshold: 0, rootMargin: '-80px 0px 0px 0px' });
        scrubberObserver.observe(faqRoot);

        if (ctaSection) {
            ctaObserver = new IntersectionObserver((entries) => {
                entries.forEach((entry) => { inCta = entry.isIntersecting; });
                applyScrubberVisibility();
            }, { root: null, threshold: 0, rootMargin: '-40% 0px -40% 0px' });
            ctaObserver.observe(ctaSection);
        }

        onScrubberScroll = applyScrubberVisibility;
        window.addEventListener('scroll', onScrubberScroll, { passive: true });
    }

    return function destroy() {
        root.removeEventListener('click', onNavClick);
        if (searchInput) searchInput.removeEventListener('input', onSearchInput);
        toggleHandlers.forEach(([item, handler]) => item.removeEventListener('toggle', handler));
        if (onScroll) window.removeEventListener('scroll', onScroll);
        if (bttBtn && onBttClick) bttBtn.removeEventListener('click', onBttClick);
        if (scrubberObserver) scrubberObserver.disconnect();
        if (ctaObserver) ctaObserver.disconnect();
        if (onScrubberScroll) window.removeEventListener('scroll', onScrubberScroll);
    };
}
