// Reusable time-based tab/slide switcher — the exact structural + timing
// mechanic built for "Who Naara Is For" (owner-supplied reference site's
// "Interactive Ecosystem" component: requestAnimationFrame progress-fill +
// live countdown, one tuned dwell time per slide), generalized so any
// marketing section can reuse the same container instead of a copy
// (owner request, 2026-09-21: "reuse that our time based tabs switcher
// container"). See resources/views/components/timed-tab-switcher.blade.php
// and the .tt-* rules in sections.css for the markup/CSS half.
export function mountTimedTabSwitcher(root) {
    const slides = root.querySelectorAll('.tt-slide');
    if (slides.length === 0) return () => {};

    const tabs = root.querySelectorAll('.tt-tab');
    const progressH = root.querySelector('.tt-progress-fill-h');
    const progressV = root.querySelector('.tt-progress-fill-v');
    const timerText = root.querySelector('.tt-timer');
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    const duration = parseInt(root.dataset.ttDuration, 10) || 11000;
    const total = slides.length;
    let current = 0;
    let startTime = null;
    let frameId = null;
    let started = false;

    const render = (timestamp) => {
        if (!startTime) startTime = timestamp;
        const elapsed = timestamp - startTime;
        const progress = Math.min(elapsed / duration, 1);

        if (progressH) progressH.style.width = `${progress * 100}%`;
        if (progressV) progressV.style.height = `${progress * 100}%`;

        const remaining = Math.ceil((duration - elapsed) / 1000);
        if (timerText) timerText.textContent = `Next in: ${Math.max(1, remaining)}s`;

        if (progress < 1) {
            frameId = requestAnimationFrame(render);
        } else {
            goTo((current + 1) % total);
        }
    };

    function goTo(index) {
        if (frameId) cancelAnimationFrame(frameId);
        startTime = null;

        slides.forEach((s) => s.classList.remove('active'));
        tabs.forEach((t) => t.classList.remove('active'));

        slides[index]?.classList.add('active');
        const tab = root.querySelector(`.tt-tab[data-slide="${index}"]`);
        tab?.classList.add('active');

        if (window.innerWidth < 1024 && tab) {
            tab.parentElement?.scrollTo({ left: tab.offsetLeft - 16, behavior: 'smooth' });
        }

        current = index;

        if (!reduceMotion) {
            frameId = requestAnimationFrame(render);
        } else if (timerText) {
            timerText.textContent = '';
        }
    }

    tabs.forEach((tab) => {
        tab.addEventListener('click', () => {
            const idx = parseInt(tab.dataset.slide, 10);
            if (!Number.isNaN(idx)) goTo(idx);
        });
    });

    // Only starts ticking once the visitor actually scrolls to it — no point
    // burning through slides autoplay before anyone can see them.
    const observer = new IntersectionObserver((entries) => {
        if (entries[0].isIntersecting && !started) {
            started = true;
            goTo(0);
            observer.disconnect();
        }
    }, { threshold: 0.2 });
    observer.observe(root);

    return () => {
        if (frameId) cancelAnimationFrame(frameId);
        observer.disconnect();
    };
}
