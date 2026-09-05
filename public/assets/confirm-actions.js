(() => {
    let confirmedForm = null;

    document.querySelectorAll('form[action$="/tenants"]').forEach(form => {
        form.method = 'post';
        form.querySelectorAll('button:not([type]), button[type="submit"]').forEach(button => {
            button.formMethod = 'post';
            button.formAction = form.action;
        });
    });

    document.addEventListener('submit', event => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) return;
        const path = new URL(form.action, window.location.href).pathname;
        if (form.method.toLowerCase() === 'get' || path === '/logout' || form.hasAttribute('data-no-confirm')) return;
        if (confirmedForm === form) {
            confirmedForm = null;
            return;
        }
        if (form.hasAttribute('onsubmit')) return;

        event.preventDefault();
        const action = event.submitter?.textContent?.trim() || 'menyimpan perubahan';
        const contextualMessage = path === '/tenants'
            ? 'Yakin melakukan check-in penghuni ini? Kamar akan langsung berstatus terisi.'
            : path.endsWith('/checkout')
                ? 'Yakin melakukan checkout pada penghuni ini? Status penghuni dan kamar akan langsung diperbarui.'
                : path.startsWith('/formpenghuni/')
                    ? 'Yakin mengirim formulir ini? Setelah dikirim, data tidak dapat diedit tanpa izin Owner/Admin.'
                    : path.endsWith('/validate')
                        ? 'Yakin memvalidasi formulir ini sebagai data yang benar?'
                        : path.endsWith('/revision')
                            ? 'Yakin membuka formulir ini untuk revisi? Status valid sebelumnya akan dibatalkan.'
                            : null;
        const message = form.dataset.confirm || contextualMessage
            || `Yakin ingin melanjutkan “${action}”? Pastikan seluruh data sudah benar sebelum dikonfirmasi.`;

        if (!window.confirm(message)) return;
        confirmedForm = form;
        form.requestSubmit(event.submitter || undefined);
    }, true);
})();
