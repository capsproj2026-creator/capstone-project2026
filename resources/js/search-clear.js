/**
 * Overlay a clear (×) on search fields. Never wraps inputs or changes holder width.
 */
const SEARCH_SELECTOR = [
    'input[type="search"]',
    'input[name="q"]',
    'input[name="search"]',
    'input[data-search-clear]',
].join(',');

const BUTTON_SIZE = 24;
const BUTTON_INSET = 8;

function isSearchLike(input) {
    if (!(input instanceof HTMLInputElement)) return false;
    if (input.dataset.noSearchClear === '1' || input.dataset.noSearchClear === 'true') return false;
    if (input.type === 'hidden' || input.type === 'password' || input.disabled || input.readOnly) return false;
    if (input.matches(SEARCH_SELECTOR)) return true;
    const placeholder = (input.getAttribute('placeholder') || '').toLowerCase();
    return placeholder.includes('search');
}

function syncClearButton(input, button) {
    const hasValue = String(input.value || '').length > 0;
    button.hidden = !hasValue;
    button.setAttribute('aria-hidden', hasValue ? 'false' : 'true');
}

function placeClearButton(input, button) {
    if (button.hidden) return;
    const parent = button.parentElement;
    if (!parent) return;

    const inputBox = input.getBoundingClientRect();
    const parentBox = parent.getBoundingClientRect();

    button.style.left = `${Math.round(inputBox.right - parentBox.left - BUTTON_SIZE - BUTTON_INSET)}px`;
    button.style.top = `${Math.round(inputBox.top - parentBox.top + (inputBox.height - BUTTON_SIZE) / 2)}px`;
}

function enhanceSearchInput(input) {
    if (!isSearchLike(input) || input.dataset.searchClearBound === '1') return;
    input.dataset.searchClearBound = '1';

    const parent = input.parentElement;
    if (!parent) return;

    parent.classList.add('sc-search-anchor');
    input.classList.add('sc-search-input');

    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'sc-search-clear';
    button.setAttribute('aria-label', 'Clear search');
    button.title = 'Clear search';
    button.hidden = true;
    button.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-3.5 w-3.5" aria-hidden="true"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z"/></svg>';

    const refresh = () => {
        syncClearButton(input, button);
        placeClearButton(input, button);
    };

    button.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopPropagation();
        const hadValue = String(input.value || '').length > 0;
        input.value = '';
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
        refresh();
        input.focus();

        const form = input.form;
        if (
            hadValue
            && form
            && String(form.method || 'get').toLowerCase() === 'get'
            && input.dataset.clearSubmit !== '0'
        ) {
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form.submit();
            }
        }
    });

    parent.appendChild(button);
    refresh();

    input.addEventListener('input', refresh);
    input.addEventListener('change', refresh);
    window.addEventListener('resize', refresh);

    if (typeof ResizeObserver === 'function') {
        const observer = new ResizeObserver(refresh);
        observer.observe(input);
        observer.observe(parent);
    }
}

function initSearchClear(root = document) {
    root.querySelectorAll('input').forEach((input) => {
        if (isSearchLike(input)) enhanceSearchInput(input);
    });
}

window.initSearchClear = initSearchClear;

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => initSearchClear());
} else {
    initSearchClear();
}

export { initSearchClear };
