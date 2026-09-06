/**
 * Overlay a clear (×) on text-like inputs across the app.
 * Never wraps inputs or changes holder width — absolute overlay only.
 * Opt out with data-no-clear / data-no-search-clear.
 */
const CLEARABLE_TYPES = new Set([
    'text',
    'search',
    'email',
    'tel',
    'url',
    'number',
    'password',
]);

const SKIP_TYPES = new Set([
    'hidden',
    'checkbox',
    'radio',
    'file',
    'submit',
    'button',
    'reset',
    'image',
    'color',
    'range',
    'date',
    'datetime-local',
    'time',
    'month',
    'week',
]);

const BUTTON_SIZE = 24;
const BUTTON_INSET = 8;

function isOptedOut(input) {
    const noClear = input.dataset.noClear ?? input.dataset.noSearchClear;
    return noClear === '1' || noClear === 'true';
}

function isSearchLike(input) {
    if (input.type === 'search') return true;
    if (input.matches('input[name="q"], input[name="search"], input[data-search-clear]')) return true;
    const placeholder = (input.getAttribute('placeholder') || '').toLowerCase();
    return placeholder.includes('search');
}

function isClearableInput(input) {
    if (!(input instanceof HTMLInputElement)) return false;
    if (isOptedOut(input)) return false;
    if (input.disabled || input.readOnly) return false;

    const type = (input.type || 'text').toLowerCase();
    if (SKIP_TYPES.has(type)) return false;
    if (CLEARABLE_TYPES.has(type)) return true;

    // Default missing/unknown type on <input> is text in browsers.
    return type === '' || type === 'text';
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

function enhanceClearableInput(input) {
    if (!isClearableInput(input) || input.dataset.searchClearBound === '1') return;
    input.dataset.searchClearBound = '1';

    const parent = input.parentElement;
    if (!parent) return;

    const searchLike = isSearchLike(input);

    parent.classList.add('sc-search-anchor');
    input.classList.add('sc-search-input');

    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'sc-search-clear';
    button.setAttribute('aria-label', searchLike ? 'Clear search' : 'Clear');
    button.title = searchLike ? 'Clear search' : 'Clear';
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

        // Only auto-resubmit GET search filters — never normal create/edit forms.
        const form = input.form;
        if (
            hadValue
            && searchLike
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
    input.addEventListener('focus', refresh);
    window.addEventListener('resize', refresh);

    if (typeof ResizeObserver === 'function') {
        const observer = new ResizeObserver(refresh);
        observer.observe(input);
        observer.observe(parent);
    }
}

function initSearchClear(root = document) {
    root.querySelectorAll('input').forEach((input) => {
        enhanceClearableInput(input);
    });
}

function watchForNewInputs() {
    if (typeof MutationObserver !== 'function') return;

    const observer = new MutationObserver((mutations) => {
        for (const mutation of mutations) {
            mutation.addedNodes.forEach((node) => {
                if (!(node instanceof HTMLElement)) return;
                if (node.matches?.('input')) {
                    enhanceClearableInput(node);
                }
                node.querySelectorAll?.('input').forEach((input) => enhanceClearableInput(input));
            });
        }
    });

    observer.observe(document.documentElement, { childList: true, subtree: true });
}

window.initSearchClear = initSearchClear;

function boot() {
    initSearchClear();
    watchForNewInputs();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
} else {
    boot();
}

export { initSearchClear };
