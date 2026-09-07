document.addEventListener('DOMContentLoaded', () => {
    const lightbox = document.getElementById('categoryPhotoLightbox');
    const items = [...document.querySelectorAll('[data-gallery-index]')];
    if (!lightbox || !items.length) return;

    const image = lightbox.querySelector('figure img');
    const caption = document.getElementById('lightboxCaption');
    const counter = document.getElementById('lightboxCounter');
    let current = 0;
    let touchStartX = null;

    const render = index => {
        current = (index + items.length) % items.length;
        const item = items[current];
        image.src = item.dataset.gallerySrc;
        image.alt = item.dataset.galleryAlt;
        caption.textContent = item.dataset.galleryAlt;
        counter.textContent = `${current + 1} / ${items.length}`;
    };

    const open = index => {
        render(index);
        lightbox.showModal();
        lightbox.querySelector('[data-lightbox-close]')?.focus();
    };

    items.forEach((item, index) => item.addEventListener('click', () => open(index)));
    lightbox.querySelector('[data-lightbox-close]')?.addEventListener('click', () => lightbox.close());
    lightbox.querySelector('[data-lightbox-prev]')?.addEventListener('click', () => render(current - 1));
    lightbox.querySelector('[data-lightbox-next]')?.addEventListener('click', () => render(current + 1));
    lightbox.addEventListener('click', event => {
        if (event.target === lightbox) lightbox.close();
    });
    lightbox.addEventListener('keydown', event => {
        if (event.key === 'ArrowLeft') render(current - 1);
        if (event.key === 'ArrowRight') render(current + 1);
    });
    lightbox.addEventListener('touchstart', event => {
        touchStartX = event.changedTouches[0]?.clientX ?? null;
    }, {passive: true});
    lightbox.addEventListener('touchend', event => {
        if (touchStartX === null) return;
        const distance = (event.changedTouches[0]?.clientX ?? touchStartX) - touchStartX;
        if (Math.abs(distance) > 45) render(current + (distance < 0 ? 1 : -1));
        touchStartX = null;
    }, {passive: true});
});
