/**
 * Overlay a clear (×) on all textboxes.
 * Password + eye: × sits left of the eye with a gap.
 * Opt out with data-no-clear / data-no-search-clear.
 * Force on with data-clear / data-search-clear, or window.enableInputClear(input).
 */
const TEXTBOX_TYPES = new Set([
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
/** Space reserved for the password eye so × and eye do not overlap. */
const EYE_RESERVE = 36;

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

function passwordToggleButton(input) {
    if (!(input instanceof HTMLInputElement)) return null;
    const wrap = input.closest('.relative');
    const fromWrap = wrap?.querySelector('[data-password-toggle]');
    if (fromWrap) return fromWrap;
    const id = input.id;
    if (!id) return null;
    return document.querySelector(`[data-password-toggle="${CSS.escape(id)}"]`);
}

function hasPasswordToggle(input) {
    return Boolean(passwordToggleButton(input));
}

function isTextbox(input) {
    if (!(input instanceof HTMLInputElement)) return false;
    const type = (input.type || 'text').toLowerCase();
    if (SKIP_TYPES.has(type)) return false;
    if (TEXTBOX_TYPES.has(type)) return true;
    return type === '' || type === 'text';
}

function isClearableInput(input) {
    if (!(input instanceof HTMLInputElement)) return false;
    if (isOptedOut(input)) return false;
    if (input.disabled || input.readOnly) return false;
    return isTextbox(input);
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
    const eyeOffset = hasPasswordToggle(input) ? EYE_RESERVE : 0;

    button.style.left = `${Math.round(inputBox.right - parentBox.left - BUTTON_SIZE - BUTTON_INSET - eyeOffset)}px`;
    button.style.top = `${Math.round(inputBox.top - parentBox.top + (inputBox.height - BUTTON_SIZE) / 2)}px`;
}

function enhanceClearableInput(input) {
    if (!isClearableInput(input) || input.dataset.searchClearBound === '1') return;
    input.dataset.searchClearBound = '1';

    const parent = input.parentElement;
    if (!parent) return;

    const searchLike = isSearchLike(input);
    const withEye = hasPasswordToggle(input);

    parent.classList.add('sc-search-anchor');
    input.classList.add('sc-search-input');
    input.classList.toggle('sc-has-password-toggle', withEye);

    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'sc-search-clear';
    if (withEye) button.classList.add('sc-search-clear--with-eye');
    button.setAttribute('aria-label', searchLike ? 'Clear search' : 'Clear');
    button.title = searchLike ? 'Clear search' : 'Clear';
    button.hidden = true;
    button.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-3.5 w-3.5" aria-hidden="true"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z"/></svg>';

    const refresh = () => {
        input.classList.toggle('sc-has-password-toggle', hasPasswordToggle(input));
        button.classList.toggle('sc-search-clear--with-eye', hasPasswordToggle(input));
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

/** Attach / re-attach clear × to one input. */
function enableInputClear(input) {
    if (!(input instanceof HTMLInputElement)) return false;
    delete input.dataset.noClear;
    delete input.dataset.noSearchClear;
    delete input.dataset.searchClearBound;
    input.parentElement?.querySelectorAll(':scope > .sc-search-clear').forEach((el) => el.remove());
    enhanceClearableInput(input);
    return input.dataset.searchClearBound === '1';
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
window.enableInputClear = enableInputClear;

function boot() {
    initSearchClear();
    watchForNewInputs();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
} else {
    boot();
}

export { initSearchClear, enableInputClear };
