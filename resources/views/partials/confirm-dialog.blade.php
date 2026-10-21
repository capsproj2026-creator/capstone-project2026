{{-- Shared confirm + toast UI for every portal layout. Use data-confirm on forms/buttons, or window.appConfirm / window.appToast from scripts. --}}
<div
    id="app-confirm-modal"
    class="fixed inset-0 z-[100] hidden items-center justify-center bg-black/50 p-4"
    role="dialog"
    aria-modal="true"
    aria-labelledby="app-confirm-title"
    aria-describedby="app-confirm-message"
>
    <div class="w-full max-w-md overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xl dark:border-slate-700 dark:bg-slate-900">
        <div class="px-6 pb-2 pt-6">
            <div class="flex items-start gap-3">
                <span
                    id="app-confirm-icon-wrap"
                    class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-amber-50 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300"
                >
                    <i data-lucide="help-circle" class="h-5 w-5" id="app-confirm-icon"></i>
                </span>
                <div class="min-w-0 flex-1">
                    <h3 id="app-confirm-title" class="text-lg font-bold text-gray-900 dark:text-white">Please confirm</h3>
                    <p id="app-confirm-message" class="mt-1 text-sm text-gray-600 dark:text-slate-300">Are you sure?</p>
                </div>
            </div>
        </div>
        <div class="mt-5 flex flex-col-reverse gap-2 border-t border-gray-100 bg-gray-50 px-6 py-4 sm:flex-row sm:justify-end dark:border-slate-800 dark:bg-slate-950/60">
            <button
                type="button"
                id="app-confirm-cancel"
                class="inline-flex items-center justify-center rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:hover:bg-slate-700"
            >
                Cancel
            </button>
            <button
                type="button"
                id="app-confirm-ok"
                class="inline-flex items-center justify-center rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700"
            >
                Confirm
            </button>
        </div>
    </div>
</div>

<div
    id="app-choice-modal"
    class="fixed inset-0 z-[100] hidden items-center justify-center bg-black/50 p-4"
    role="dialog"
    aria-modal="true"
    aria-labelledby="app-choice-title"
>
    <div class="w-full max-w-md overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xl dark:border-slate-700 dark:bg-slate-900">
        <div class="px-6 pb-2 pt-6">
            <h3 id="app-choice-title" class="text-lg font-bold text-gray-900 dark:text-white">Choose an option</h3>
            <p id="app-choice-message" class="mt-1 text-sm text-gray-600 dark:text-slate-300"></p>
            <div id="app-choice-options" class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-2"></div>
        </div>
        <div class="mt-5 flex justify-end border-t border-gray-100 bg-gray-50 px-6 py-4 dark:border-slate-800 dark:bg-slate-950/60">
            <button
                type="button"
                id="app-choice-cancel"
                class="inline-flex items-center justify-center rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:hover:bg-slate-700"
            >
                Cancel
            </button>
        </div>
    </div>
</div>

<div
    id="app-toast"
    class="pointer-events-none fixed inset-x-0 top-4 z-[110] flex justify-center px-4"
    aria-live="polite"
>
    <div
        id="app-toast-panel"
        class="pointer-events-auto hidden max-w-md translate-y-[-8px] rounded-xl border px-4 py-3 text-sm font-medium shadow-lg opacity-0 transition duration-200"
        role="status"
    >
        <p id="app-toast-message"></p>
    </div>
</div>

<script>
(() => {
    if (window.appConfirm) return;

    const modal = document.getElementById('app-confirm-modal');
    const titleEl = document.getElementById('app-confirm-title');
    const messageEl = document.getElementById('app-confirm-message');
    const okBtn = document.getElementById('app-confirm-ok');
    const cancelBtn = document.getElementById('app-confirm-cancel');
    const iconWrap = document.getElementById('app-confirm-icon-wrap');
    const toast = document.getElementById('app-toast-panel');
    const toastMsg = document.getElementById('app-toast-message');
    let resolveConfirm = null;
    let toastTimer = null;

    const tones = {
        success: {
            ok: 'bg-emerald-600 hover:bg-emerald-700',
            iconWrap: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
            icon: 'check-circle',
        },
        danger: {
            ok: 'bg-rose-600 hover:bg-rose-700',
            iconWrap: 'bg-rose-50 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300',
            icon: 'alert-triangle',
        },
        warning: {
            ok: 'bg-amber-600 hover:bg-amber-700',
            iconWrap: 'bg-amber-50 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300',
            icon: 'help-circle',
        },
        info: {
            ok: 'bg-blue-600 hover:bg-blue-700',
            iconWrap: 'bg-blue-50 text-blue-700 dark:bg-blue-500/15 dark:text-blue-300',
            icon: 'info',
        },
    };

    const closeModal = (result) => {
        if (!modal) return;
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.removeEventListener('keydown', onKeydown);
        const resolve = resolveConfirm;
        resolveConfirm = null;
        if (resolve) resolve(!!result);
    };

    const onKeydown = (event) => {
        if (event.key === 'Escape') {
            event.preventDefault();
            closeModal(false);
        }
        if (event.key === 'Enter') {
            event.preventDefault();
            closeModal(true);
        }
    };

    const openModal = (options = {}) => {
        if (!modal || !titleEl || !messageEl || !okBtn || !cancelBtn) {
            return Promise.resolve(window.confirm(options.message || 'Are you sure?'));
        }

        const tone = tones[options.tone] || tones.warning;
        titleEl.textContent = options.title || 'Please confirm';
        messageEl.textContent = options.message || 'Are you sure?';
        okBtn.textContent = options.confirmLabel || 'Confirm';
        cancelBtn.textContent = options.cancelLabel || 'Cancel';
        okBtn.className = `inline-flex items-center justify-center rounded-xl px-4 py-2.5 text-sm font-semibold text-white ${tone.ok}`;
        if (iconWrap) {
            iconWrap.className = `mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-full ${tone.iconWrap}`;
            iconWrap.innerHTML = `<i data-lucide="${tone.icon}" class="h-5 w-5"></i>`;
            if (window.lucide) window.lucide.createIcons({ nodes: [iconWrap] });
        }

        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.addEventListener('keydown', onKeydown);
        okBtn.focus();

        return new Promise((resolve) => {
            resolveConfirm = resolve;
        });
    };

    window.appConfirm = (options) => {
        if (typeof options === 'string') {
            return openModal({ message: options });
        }
        return openModal(options || {});
    };

    window.appToast = (message, type = 'success') => {
        if (!toast || !toastMsg) {
            window.alert(message);
            return;
        }
        const styles = {
            success: 'border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-500/40 dark:bg-emerald-950 dark:text-emerald-100',
            error: 'border-rose-200 bg-rose-50 text-rose-900 dark:border-rose-500/40 dark:bg-rose-950 dark:text-rose-100',
            info: 'border-blue-200 bg-blue-50 text-blue-900 dark:border-blue-500/40 dark:bg-blue-950 dark:text-blue-100',
        };
        toastMsg.textContent = message || '';
        toast.className = `pointer-events-auto max-w-md rounded-xl border px-4 py-3 text-sm font-medium shadow-lg transition duration-200 ${styles[type] || styles.success}`;
        toast.classList.remove('hidden');
        requestAnimationFrame(() => {
            toast.classList.remove('translate-y-[-8px]', 'opacity-0');
            toast.classList.add('translate-y-0', 'opacity-100');
        });
        window.clearTimeout(toastTimer);
        toastTimer = window.setTimeout(() => {
            toast.classList.add('translate-y-[-8px]', 'opacity-0');
            window.setTimeout(() => toast.classList.add('hidden'), 200);
        }, 3500);
    };

    window.appAlert = (message, options = {}) => openModal({
        title: options.title || 'Notice',
        message,
        confirmLabel: options.confirmLabel || 'OK',
        cancelLabel: options.cancelLabel || 'Close',
        tone: options.tone || 'info',
    }).then(() => true);

    const choiceModal = document.getElementById('app-choice-modal');
    const choiceTitle = document.getElementById('app-choice-title');
    const choiceMessage = document.getElementById('app-choice-message');
    const choiceOptions = document.getElementById('app-choice-options');
    const choiceCancel = document.getElementById('app-choice-cancel');
    let resolveChoice = null;

    const closeChoice = (value) => {
        if (!choiceModal) return;
        choiceModal.classList.add('hidden');
        choiceModal.classList.remove('flex');
        document.removeEventListener('keydown', onChoiceKeydown);
        const resolve = resolveChoice;
        resolveChoice = null;
        if (resolve) resolve(value == null ? null : String(value));
    };

    const onChoiceKeydown = (event) => {
        if (event.key === 'Escape') {
            event.preventDefault();
            closeChoice(null);
        }
    };

    const optionStyles = {
        Available: 'border-emerald-200 bg-emerald-50 text-emerald-800 hover:bg-emerald-100 dark:border-emerald-500/40 dark:bg-emerald-950 dark:text-emerald-100',
        Occupied: 'border-rose-200 bg-rose-50 text-rose-800 hover:bg-rose-100 dark:border-rose-500/40 dark:bg-rose-950 dark:text-rose-100',
        Reserved: 'border-blue-200 bg-blue-50 text-blue-800 hover:bg-blue-100 dark:border-blue-500/40 dark:bg-blue-950 dark:text-blue-100',
        Maintenance: 'border-slate-300 bg-slate-100 text-slate-800 hover:bg-slate-200 dark:border-slate-500/40 dark:bg-slate-800 dark:text-slate-100',
        default: 'border-gray-200 bg-white text-gray-800 hover:bg-gray-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100',
    };

    window.appChoice = (options = {}) => {
        if (!choiceModal || !choiceTitle || !choiceMessage || !choiceOptions) {
            const list = (options.options || []).map((item) => (typeof item === 'string' ? item : item.value)).join(' | ');
            const typed = window.prompt(
                `${options.message || options.title || 'Choose an option'}\n${list}`,
                options.current || ''
            );
            return Promise.resolve(typed == null || typed === '' ? null : typed);
        }

        choiceTitle.textContent = options.title || 'Choose an option';
        choiceMessage.textContent = options.message || '';
        choiceMessage.classList.toggle('hidden', !options.message);
        choiceOptions.replaceChildren();

        (options.options || []).forEach((item) => {
            const value = typeof item === 'string' ? item : item.value;
            const label = typeof item === 'string' ? item : (item.label || item.value);
            const button = document.createElement('button');
            button.type = 'button';
            const selected = String(options.current || '') === String(value);
            button.className = `rounded-xl border px-4 py-3 text-sm font-semibold transition ${optionStyles[value] || optionStyles.default}${selected ? ' ring-2 ring-offset-2 ring-gray-400' : ''}`;
            button.textContent = label;
            if (selected) button.setAttribute('aria-current', 'true');
            button.addEventListener('click', () => closeChoice(value));
            choiceOptions.append(button);
        });

        choiceModal.classList.remove('hidden');
        choiceModal.classList.add('flex');
        document.addEventListener('keydown', onChoiceKeydown);

        return new Promise((resolve) => {
            resolveChoice = resolve;
        });
    };

    choiceCancel?.addEventListener('click', () => closeChoice(null));
    choiceModal?.addEventListener('click', (event) => {
        if (event.target === choiceModal) closeChoice(null);
    });

    okBtn?.addEventListener('click', () => closeModal(true));
    cancelBtn?.addEventListener('click', () => closeModal(false));
    modal?.addEventListener('click', (event) => {
        if (event.target === modal) closeModal(false);
    });

    const readOptions = (el) => ({
        title: el.getAttribute('data-confirm-title') || 'Please confirm',
        message: el.getAttribute('data-confirm') || 'Are you sure?',
        confirmLabel: el.getAttribute('data-confirm-action') || 'Confirm',
        cancelLabel: el.getAttribute('data-confirm-cancel') || 'Cancel',
        tone: el.getAttribute('data-confirm-tone') || 'warning',
    });

    document.addEventListener('submit', async (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) return;
        if (form.dataset.confirmBypass === '1') {
            delete form.dataset.confirmBypass;
            return;
        }

        const submitter = event.submitter instanceof HTMLElement ? event.submitter : null;
        const source = submitter?.hasAttribute('data-confirm')
            ? submitter
            : (form.hasAttribute('data-confirm') ? form : null);
        if (!source) return;

        event.preventDefault();
        event.stopPropagation();

        const ok = await window.appConfirm(readOptions(source));
        if (!ok) return;

        form.dataset.confirmBypass = '1';
        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit(submitter || undefined);
        } else {
            form.submit();
        }
    }, true);

    document.addEventListener('click', async (event) => {
        const button = event.target.closest('button[data-confirm], a[data-confirm], input[type="submit"][data-confirm]');
        if (!button) return;
        if (button.closest('form')) return; // form submit handler covers these

        const formId = button.getAttribute('form');
        if (formId) {
            const form = document.getElementById(formId);
            if (form) {
                event.preventDefault();
                event.stopPropagation();
                const ok = await window.appConfirm(readOptions(button));
                if (!ok) return;
                form.dataset.confirmBypass = '1';
                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit(button);
                } else {
                    form.submit();
                }
                return;
            }
        }

        if (button.tagName === 'A') {
            event.preventDefault();
            event.stopPropagation();
            const ok = await window.appConfirm(readOptions(button));
            if (ok) window.location.href = button.href;
        }
    }, true);
})();
</script>
