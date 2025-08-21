// Menu mobile
const toggle = document.querySelector('.menu-toggle');
const nav = document.querySelector('[data-nav]');
toggle.addEventListener('click', () => {
    const open = toggle.getAttribute('aria-expanded') === 'true';
    toggle.setAttribute('aria-expanded', String(!open));
    nav.classList.toggle('open');
});

// Révélations au scroll (fade & translate)
const io = new IntersectionObserver((entries) => {
    entries.forEach(e => {
        if (e.isIntersecting) e.target.classList.add('reveal--visible');
    });
}, { threshold: 0.15 });
document.querySelectorAll('.reveal').forEach(el => io.observe(el));

// Carrousel minimal
const track = document.querySelector('[data-track]');
const slides = Array.from(track.children);
let index = 0;

function goto(i) {
    index = (i + slides.length) % slides.length;
    track.style.transform = `translateX(${index * -100}%)`;
}
document.querySelector('[data-prev]').addEventListener('click', () => goto(index - 1));
document.querySelector('[data-next]').addEventListener('click', () => goto(index + 1));
setInterval(() => goto(index + 1), 5000); // auto-slide
