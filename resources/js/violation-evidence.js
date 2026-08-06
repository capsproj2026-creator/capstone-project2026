/**
 * Violation evidence lightbox with multi-image browsing.
 */
function initViolationEvidence(root = document) {
    const modal = document.getElementById('violation-evidence-modal');
    if (!modal || modal.dataset.bound === '1') {
        return;
    }
    modal.dataset.bound = '1';

    const imageEl = document.getElementById('violation-evidence-image');
    const titleEl = document.getElementById('violation-evidence-modal-title');
    const counterEl = document.getElementById('violation-evidence-modal-counter');
    const thumbsEl = document.getElementById('violation-evidence-thumbs');
    const prevBtn = document.getElementById('violation-evidence-prev');
    const nextBtn = document.getElementById('violation-evidence-next');
    const openTab = document.getElementById('violation-evidence-open-tab');
    const closeButtons = [
        document.getElementById('violation-evidence-modal-close'),
        document.getElementById('violation-evidence-close-btn'),
    ].filter(Boolean);

    let urls = [];
    let index = 0;

    const render = () => {
        if (!urls.length || !imageEl) {
            return;
        }

        index = Math.max(0, Math.min(index, urls.length - 1));
        imageEl.src = urls[index];
        imageEl.alt = `Violation evidence ${index + 1} of ${urls.length}`;

        if (counterEl) {
            counterEl.textContent = urls.length > 1 ? `Image ${index + 1} of ${urls.length}` : '';
        }

        if (openTab) {
            openTab.href = urls[index];
        }

        const multi = urls.length > 1;
        prevBtn?.classList.toggle('hidden', !multi);
        nextBtn?.classList.toggle('hidden', !multi);
        thumbsEl?.classList.toggle('hidden', !multi);

        if (thumbsEl && multi) {
            thumbsEl.innerHTML = urls
                .map(
                    (url, i) => `
                <button type="button" data-evidence-thumb="${i}" class="shrink-0 overflow-hidden rounded-lg border-2 ${i === index ? 'border-blue-500' : 'border-transparent opacity-75 hover:opacity-100'}">
                    <img src="${url}" alt="" class="h-14 w-20 object-cover" loading="lazy" decoding="async">
                </button>`
                )
                .join('');

            thumbsEl.querySelectorAll('[data-evidence-thumb]').forEach((btn) => {
                btn.addEventListener('click', () => {
                    index = parseInt(btn.getAttribute('data-evidence-thumb') || '0', 10);
                    render();
                });
            });
        }

        if (window.lucide?.createIcons) {
            window.lucide.createIcons();
        }
    };

    const open = (nextUrls, title = 'Violation Evidence') => {
        urls = Array.isArray(nextUrls) ? nextUrls.filter(Boolean) : [];
        if (!urls.length) {
            return;
        }

        index = 0;
        if (titleEl) {
            titleEl.textContent = title;
        }
        render();
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('overflow-hidden');
        window.dispatchEvent(new Event('portal:collapse-sidebar'));
    };

    const close = () => {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('overflow-hidden');
        if (imageEl) {
            imageEl.removeAttribute('src');
        }
    };

    root.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-violation-evidence-open]');
        if (!trigger) {
            return;
        }

        event.preventDefault();
        try {
            const parsed = JSON.parse(trigger.getAttribute('data-evidence-urls') || '[]');
            open(parsed, trigger.getAttribute('data-evidence-title') || 'Violation Evidence');
        } catch (e) {
            // ignore malformed payload
        }
    });

    prevBtn?.addEventListener('click', () => {
        index = (index - 1 + urls.length) % urls.length;
        render();
    });

    nextBtn?.addEventListener('click', () => {
        index = (index + 1) % urls.length;
        render();
    });

    closeButtons.forEach((btn) => btn.addEventListener('click', close));
    modal.addEventListener('click', (event) => {
        if (event.target === modal) {
            close();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (modal.classList.contains('hidden')) {
            return;
        }

        if (event.key === 'Escape') {
            close();
        } else if (event.key === 'ArrowLeft' && urls.length > 1) {
            index = (index - 1 + urls.length) % urls.length;
            render();
        } else if (event.key === 'ArrowRight' && urls.length > 1) {
            index = (index + 1) % urls.length;
            render();
        }
    });
}

window.initViolationEvidence = initViolationEvidence;

document.addEventListener('DOMContentLoaded', () => initViolationEvidence());
