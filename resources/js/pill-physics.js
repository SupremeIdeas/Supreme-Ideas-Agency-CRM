// About page "The Naara Promise" pills — Matter.js playground. The pills
// are real DOM nodes (best anti-aliasing + hover styling) tracked by Matter
// bodies; they fall in when the section scrolls into view and can be
// grabbed and thrown. Static-layout fallback is the server-rendered flex
// wrap, which stays in place if this bundle never loads or the visitor
// prefers reduced motion.
import Matter from 'matter-js';

export function mountPillPhysics(root) {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return () => {};

    const pills = Array.from(root.querySelectorAll('[data-pill]'));
    if (!pills.length) return () => {};

    const { Engine, Runner, Bodies, Body, Composite, Mouse, MouseConstraint } = Matter;
    let engine = null, runner = null, raf = 0, started = false, io = null;
    const nodes = [];

    const start = () => {
        if (started) return;
        started = true;
        const sizes = pills.map((el) => [el.offsetWidth, el.offsetHeight]);
        root.classList.add('is-physics');
        const w = root.clientWidth, h = root.clientHeight;
        engine = Engine.create({ gravity: { y: 1 } });
        const wall = (x, y, ww, hh) => Bodies.rectangle(x, y, ww, hh, { isStatic: true });
        Composite.add(engine.world, [wall(w / 2, h + 25, w + 200, 50), wall(-25, h / 2, 50, h * 3), wall(w + 25, h / 2, 50, h * 3)]);

        pills.forEach((el, i) => {
            const [pw, ph] = sizes[i];
            const body = Bodies.rectangle(pw / 2 + Math.random() * Math.max(1, w - pw), -ph - i * 60, pw, ph,
                { chamfer: { radius: ph / 2 }, restitution: 0.45, friction: 0.2, angle: (Math.random() - 0.5) * 0.6 });
            el.style.position = 'absolute'; el.style.left = '0'; el.style.top = '0';
            el.style.cursor = 'grab'; el.style.touchAction = 'none';
            nodes.push({ el, body, pw, ph });
            Composite.add(engine.world, body);
        });

        const mouse = Mouse.create(root);
        // Let the page scroll normally unless a pill is actually grabbed.
        mouse.element.removeEventListener('mousewheel', mouse.mousewheel);
        mouse.element.removeEventListener('DOMMouseScroll', mouse.mousewheel);
        const mc = MouseConstraint.create(engine, { mouse, constraint: { stiffness: 0.18, render: { visible: false } } });
        Composite.add(engine.world, mc);

        runner = Runner.create();
        Runner.run(runner, engine);
        const draw = () => {
            nodes.forEach(({ el, body, pw, ph }) => {
                el.style.transform = `translate(${body.position.x - pw / 2}px, ${body.position.y - ph / 2}px) rotate(${body.angle}rad)`;
            });
            raf = requestAnimationFrame(draw);
        };
        draw();
    };

    io = new IntersectionObserver((entries) => {
        if (entries.some((e) => e.isIntersecting)) { start(); io.disconnect(); }
    }, { threshold: 0.35 });
    io.observe(root);

    return () => {
        io && io.disconnect();
        cancelAnimationFrame(raf);
        if (runner) Runner.stop(runner);
        if (engine) { Composite.clear(engine.world, false); Engine.clear(engine); }
        root.classList.remove('is-physics');
        nodes.forEach(({ el }) => {
            ['position', 'left', 'top', 'cursor', 'touchAction', 'transform'].forEach((k) => { el.style[k] = ''; });
        });
    };
}
