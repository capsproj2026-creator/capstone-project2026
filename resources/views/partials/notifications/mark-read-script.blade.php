<script>
(() => {
    const badge = document.getElementById('notification-badge');

    const updateBadge = (count) => {
        if (!badge) return;
        const n = Math.max(0, Number(count) || 0);
        badge.dataset.notificationCount = String(n);
        if (n < 1) {
            badge.classList.add('hidden');
            badge.textContent = '0';
            return;
        }
        badge.classList.remove('hidden');
        badge.textContent = n > 9 ? '9+' : String(n);
    };

    const markRowRead = (form) => {
        const row = form.closest('[data-notification-row]');
        if (!row) return;
        row.classList.remove('border-l-4', 'border-l-blue-600', 'bg-blue-50/50');
        form.remove();
    };

    document.querySelectorAll('form[data-notification-action]').forEach((form) => {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
            }

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': form.querySelector('input[name="_token"]')?.value || '',
                    },
                    body: new FormData(form),
                    credentials: 'same-origin',
                });

                if (!response.ok) {
                    throw new Error('Request failed');
                }

                const data = await response.json();
                updateBadge(data.unread_count);

                if (form.action.includes('mark_all_read')) {
                    document.querySelectorAll('[data-notification-row]').forEach((row) => {
                        row.classList.remove('border-l-4', 'border-l-blue-600', 'bg-blue-50/50');
                        row.querySelector('form[data-notification-action]')?.remove();
                    });
                    form.querySelector('button[type="submit"]')?.setAttribute('disabled', 'disabled');
                } else if (form.action.includes('mark_read')) {
                    markRowRead(form);
                } else {
                    window.location.reload();
                    return;
                }
            } catch (error) {
                form.submit();
            } finally {
                if (submitBtn && !form.action.includes('mark_all_read')) {
                    submitBtn.disabled = false;
                }
            }
        });
    });
})();
</script>
