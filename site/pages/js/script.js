// js/script.js

function on(sel, ev, fn, root = document) {
    const el = root.querySelector(sel);
    if (el) el.addEventListener(ev, fn);
    return el;
}

document.addEventListener('DOMContentLoaded', () => {
    // Menu mobile
    const toggle = document.querySelector('.menu-toggle');
    const nav    = document.querySelector('[data-nav]');
    if (toggle && nav) {
        toggle.addEventListener('click', () => {
            const open = toggle.getAttribute('aria-expanded') === 'true';
            toggle.setAttribute('aria-expanded', String(!open));
            nav.classList.toggle('open');
        });
    }

    // Révélations au scroll
    if ('IntersectionObserver' in window) {
        const io = new IntersectionObserver((entries) => {
            entries.forEach(e => { if (e.isIntersecting) e.target.classList.add('reveal--visible'); });
        }, { threshold: 0.15 });
        document.querySelectorAll('.reveal').forEach(el => io.observe(el));
        // Expo pour autres scripts si besoin
        window.io = io;
    }

    // Carrousel (seulement si présent)
    const track = document.querySelector('[data-track]');
    if (track) {
        const slides = Array.from(track.children);
        let index = 0;
        function goto(i) {
            index = (i + slides.length) % slides.length;
            track.style.transform = `translateX(${index * -100}%)`;
            window.index = index;
        }
        on('[data-prev]', 'click', () => goto(index - 1));
        on('[data-next]', 'click', () => goto(index + 1));
        setInterval(() => goto(index + 1), 5000);
        window.goto = goto;
    }
});
