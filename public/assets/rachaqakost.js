document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-open]').forEach(button => {
        button.onclick = () => document.getElementById(button.dataset.open)?.showModal();
    });
    document.querySelectorAll('[data-close]').forEach(button => {
        button.onclick = () => button.closest('dialog')?.close();
    });
    document.querySelectorAll('dialog').forEach(dialog => {
        dialog.onclick = event => {
            if (event.target === dialog) dialog.close();
        };
    });

    const digitsOnly = value => String(value ?? '').replace(/\D/g, '');
    const formatCurrency = input => {
        const digits = digitsOnly(input.value).replace(/^0+(?=\d)/, '');
        input.value = digits ? new Intl.NumberFormat('id-ID').format(Number(digits)) : '';
    };

    document.querySelectorAll('[data-currency]').forEach(input => {
        formatCurrency(input);
        input.addEventListener('input', () => {
            input.dataset.autoValue = '';
            formatCurrency(input);
        });
    });
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', () => {
            form.querySelectorAll('[data-currency]').forEach(input => {
                input.value = digitsOnly(input.value);
            });
        });
    });

    const tenantSelect = document.getElementById('paymentTenant');
    const monthsInput = document.getElementById('paymentMonths');
    const periodInput = document.getElementById('paymentPeriod');
    const paymentAmount = document.querySelector('#paymentModal [name="amount"]');
    const monthFormatter = new Intl.DateTimeFormat('id-ID', {month: 'long', year: 'numeric'});
    const updatePaymentPeriod = () => {
        if (!tenantSelect || !monthsInput || !periodInput) return;
        const option = tenantSelect.selectedOptions[0];
        const due = option?.dataset.due;
        const months = Math.max(1, Number(monthsInput.value) || 1);

        if (!due) {
            periodInput.value = 'Pilih penghuni terlebih dahulu';
            if (paymentAmount?.dataset.autoValue) paymentAmount.value = '';
            return;
        }

        const [year, month] = due.split('-').map(Number);
        const start = new Date(year, month - 1, 1);
        const end = new Date(year, month - 1 + months - 1, 1);
        const startLabel = monthFormatter.format(start);
        const endLabel = monthFormatter.format(end);
        periodInput.value = months === 1 ? startLabel : `${startLabel} – ${endLabel}`;

        if (paymentAmount && option.dataset.price) {
            const autoValue = String(Number(option.dataset.price) * months);
            paymentAmount.value = autoValue;
            paymentAmount.dataset.autoValue = autoValue;
            formatCurrency(paymentAmount);
        }
    };
    tenantSelect?.addEventListener('change', updatePaymentPeriod);
    monthsInput?.addEventListener('input', updatePaymentPeriod);
    updatePaymentPeriod();

    const templateInput = document.querySelector('[name="template"]');
    const templatePreview = document.getElementById('whatsappTemplatePreview');
    const updateTemplatePreview = () => {
        if (!templateInput || !templatePreview) return;
        const samples = {
            '{nama}': 'Budi',
            '{kamar}': 'A.01',
            '{kategori}': 'VIP',
            '{nominal}': 'Rp 1.500.000',
            '{jatuh_tempo}': '20 Agustus 2026',
            '{status}': 'akan jatuh tempo dalam 3 hari',
        };
        templatePreview.textContent = Object.entries(samples).reduce(
            (message, [placeholder, value]) => message.split(placeholder).join(value),
            templateInput.value,
        );
    };
    templateInput?.addEventListener('input', updateTemplatePreview);
    updateTemplatePreview();

    const cashflowValue = document.getElementById('cashflowValue');
    document.querySelectorAll('[data-cashflow-bar]').forEach(bar => {
        bar.addEventListener('click', () => {
            document.querySelectorAll('[data-cashflow-bar]').forEach(item => item.classList.remove('active'));
            bar.classList.add('active');
            if (cashflowValue) {
                cashflowValue.textContent = bar.dataset.tooltip;
                cashflowValue.hidden = false;
            }
        });
    });

    const toast = document.querySelector('.toast');
    if (toast) setTimeout(() => toast.classList.add('hide'), 4200);
});
