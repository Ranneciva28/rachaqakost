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

    const parseLocalDate = value => {
        const [year, month, day] = String(value || '').split('-').map(Number);
        return year && month && day ? new Date(year, month - 1, day) : null;
    };
    const toDateValue = date => {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    };
    const addCycle = (date, cycle, count) => {
        const result = new Date(date);
        if (cycle === 'DAILY') result.setDate(result.getDate() + count);
        else if (cycle === 'WEEKLY') result.setDate(result.getDate() + (count * 7));
        else {
            const originalDay = result.getDate();
            result.setDate(1);
            result.setMonth(result.getMonth() + count);
            const lastDay = new Date(result.getFullYear(), result.getMonth() + 1, 0).getDate();
            result.setDate(Math.min(originalDay, lastDay));
        }
        return result;
    };

    const tenantSelect = document.getElementById('paymentTenant');
    const paymentMode = document.getElementById('paymentMode');
    const paymentModeHelp = document.getElementById('paymentModeHelp');
    const historicalFields = document.getElementById('historicalPaymentFields');
    const paymentCycle = document.getElementById('paymentBillingCycle');
    const historicalPeriodStart = document.getElementById('historicalPeriodStart');
    const periodsInput = document.getElementById('paymentPeriods');
    const periodsLabel = document.getElementById('paymentPeriodsLabel');
    const cycleHelp = document.getElementById('paymentCycleHelp');
    const periodInput = document.getElementById('paymentPeriod');
    const paymentAmount = document.querySelector('#paymentModal [name="amount"]');
    const monthFormatter = new Intl.DateTimeFormat('id-ID', {month: 'long', year: 'numeric'});
    const dateFormatter = new Intl.DateTimeFormat('id-ID', {day: 'numeric', month: 'long', year: 'numeric'});
    const updatePaymentPeriod = () => {
        if (!tenantSelect || !periodsInput || !periodInput) return;
        const option = tenantSelect.selectedOptions[0];
        const historical = paymentMode?.value === 'HISTORICAL';
        const due = historical ? historicalPeriodStart?.value : option?.dataset.due;
        const cycle = paymentCycle?.value || option?.dataset.cycle || 'MONTHLY';
        const periods = Math.max(1, Number(periodsInput.value) || 1);
        const cycleConfig = {
            DAILY: {label: 'Jumlah hari', max: 365, help: 'Tarif harian × jumlah hari.'},
            WEEKLY: {label: 'Jumlah minggu', max: 52, help: 'Tarif mingguan × jumlah minggu.'},
            MONTHLY: {label: 'Jumlah bulan', max: 24, help: 'Tarif bulanan × jumlah bulan.'},
        }[cycle];
        periodsInput.max = String(cycleConfig.max);
        if (Number(periodsInput.value) > cycleConfig.max) periodsInput.value = String(cycleConfig.max);
        if (periodsLabel) periodsLabel.textContent = cycleConfig.label;
        if (cycleHelp) cycleHelp.textContent = `${cycleConfig.help} Periode dihitung otomatis dan tidak dapat diedit.`;

        if (!due) {
            periodInput.value = 'Pilih penghuni terlebih dahulu';
            if (paymentAmount?.dataset.autoValue) paymentAmount.value = '';
            return;
        }

        const count = Math.min(periods, cycleConfig.max);
        const start = parseLocalDate(due);
        if (!start) return;
        if (cycle === 'MONTHLY') {
            const end = addCycle(start, cycle, count - 1);
            const startLabel = monthFormatter.format(start);
            const endLabel = monthFormatter.format(end);
            periodInput.value = count === 1 ? startLabel : `${startLabel} – ${endLabel}`;
        } else {
            const end = addCycle(start, cycle, count);
            end.setDate(end.getDate() - 1);
            periodInput.value = count === 1 && cycle === 'DAILY'
                ? dateFormatter.format(start)
                : `${dateFormatter.format(start)} – ${dateFormatter.format(end)}`;
        }

        const price = option?.dataset[cycle.toLowerCase()];
        if (paymentAmount && price) {
            const autoValue = String(Number(price) * count);
            paymentAmount.value = autoValue;
            paymentAmount.dataset.autoValue = autoValue;
            formatCurrency(paymentAmount);
        } else if (paymentAmount?.dataset.autoValue) {
            paymentAmount.value = '';
            paymentAmount.dataset.autoValue = '';
        }
    };
    const updatePaymentMode = () => {
        if (!paymentMode || !tenantSelect) return;
        const historical = paymentMode.value === 'HISTORICAL';
        if (historicalFields) historicalFields.hidden = !historical;
        if (historicalPeriodStart) historicalPeriodStart.required = historical;
        tenantSelect.querySelectorAll('option').forEach(option => {
            option.disabled = !historical && option.value !== '' && option.dataset.active !== '1';
        });
        if (tenantSelect.selectedOptions[0]?.disabled) tenantSelect.value = '';
        if (paymentModeHelp) paymentModeHelp.textContent = historical
            ? 'Histori menyimpan tanggal dan periode lama tanpa mengubah jatuh tempo penghuni.'
            : 'Pembayaran reguler memajukan jatuh tempo penghuni sesuai jumlah periode.';
        updatePaymentPeriod();
    };
    const updatePaymentTenant = () => {
        const option = tenantSelect?.selectedOptions[0];
        if (paymentCycle && option?.value) {
            paymentCycle.value = option.dataset.cycle || 'MONTHLY';
        }
        updatePaymentPeriod();
    };
    tenantSelect?.addEventListener('change', updatePaymentTenant);
    periodsInput?.addEventListener('input', updatePaymentPeriod);
    paymentMode?.addEventListener('change', updatePaymentMode);
    paymentCycle?.addEventListener('change', updatePaymentPeriod);
    historicalPeriodStart?.addEventListener('change', updatePaymentPeriod);
    updatePaymentMode();

    const tenantRoom = document.getElementById('tenantRoom');
    const tenantBillingCycle = document.getElementById('tenantBillingCycle');
    const tenantRatePreview = document.getElementById('tenantRatePreview');
    const tenantMoveIn = document.getElementById('tenantMoveIn');
    const tenantNextDue = document.getElementById('tenantNextDue');
    const cycleLabels = {DAILY: 'harian', WEEKLY: 'mingguan', MONTHLY: 'bulanan'};
    const updateTenantBilling = () => {
        if (!tenantRoom || !tenantBillingCycle) return;
        const room = tenantRoom.selectedOptions[0];
        const cycle = tenantBillingCycle.value;
        const price = Number(room?.dataset[cycle.toLowerCase()] || 0);
        if (tenantRatePreview) {
            tenantRatePreview.textContent = !room?.value
                ? 'Pilih kamar untuk melihat tarif.'
                : price > 0
                    ? `Tarif ${cycleLabels[cycle]}: Rp ${new Intl.NumberFormat('id-ID').format(price)} per periode.`
                    : `Tarif ${cycleLabels[cycle]} belum diatur; nominal pembayaran nanti diisi manual.`;
        }
        const moveIn = parseLocalDate(tenantMoveIn?.value);
        if (moveIn && tenantNextDue) tenantNextDue.value = toDateValue(addCycle(moveIn, cycle, 1));
    };
    tenantRoom?.addEventListener('change', updateTenantBilling);
    tenantBillingCycle?.addEventListener('change', updateTenantBilling);
    tenantMoveIn?.addEventListener('change', updateTenantBilling);
    updateTenantBilling();

    const templateInput = document.querySelector('[name="template"]');
    const templatePreview = document.getElementById('whatsappTemplatePreview');
    const updateTemplatePreview = () => {
        if (!templateInput || !templatePreview) return;
        const samples = {
            '{nama}': 'Budi',
            '{kamar}': 'A.01',
            '{kategori}': 'VIP',
            '{nominal}': 'Rp 1.500.000',
            '{siklus}': 'bulanan',
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

    const tenantEditModal = document.getElementById('tenantEditModal');
    const tenantEditForm = document.getElementById('tenantEditForm');
    const tenantEditRoom = document.getElementById('tenantEditRoom');
    const tenantEditDueField = document.getElementById('tenantEditDueField');
    const tenantEditOutField = document.getElementById('tenantEditOutField');
    const tenantEditStatus = document.getElementById('tenantEditStatus');
    const tenantEditSubtitle = document.getElementById('tenantEditSubtitle');
    document.querySelectorAll('.tenant-edit-button').forEach(button => {
        button.addEventListener('click', () => {
            if (!tenantEditModal || !tenantEditForm || !tenantEditRoom) return;
            const active = button.dataset.active === '1';
            const currentRoom = button.dataset.room;
            tenantEditForm.action = button.dataset.action;
            tenantEditForm.elements.namedItem('name').value = button.dataset.name || '';
            tenantEditForm.elements.namedItem('phone').value = button.dataset.phone || '';
            tenantEditForm.elements.namedItem('identity_number').value = button.dataset.identity || '';
            tenantEditForm.elements.namedItem('move_in').value = button.dataset.moveIn || '';
            tenantEditForm.elements.namedItem('next_due').value = button.dataset.nextDue || '';
            tenantEditForm.elements.namedItem('move_out').value = button.dataset.moveOut || '';
            tenantEditForm.elements.namedItem('billing_cycle').value = button.dataset.cycle || 'MONTHLY';
            tenantEditForm.elements.namedItem('move_out').required = !active;
            tenantEditRoom.value = currentRoom;
            tenantEditRoom.querySelectorAll('option').forEach(option => {
                option.disabled = active && option.value !== currentRoom && option.dataset.status !== 'KOSONG';
            });
            if (tenantEditDueField) tenantEditDueField.hidden = !active;
            if (tenantEditOutField) tenantEditOutField.hidden = active;
            if (tenantEditStatus) tenantEditStatus.textContent = active
                ? 'Penghuni aktif · perubahan kamar akan menyinkronkan status kamar otomatis.'
                : 'Riwayat check-out · perubahan tidak memengaruhi status kamar saat ini.';
            if (tenantEditSubtitle) tenantEditSubtitle.textContent = active
                ? 'Perbarui identitas, kontak, kamar, atau jatuh tempo penghuni aktif.'
                : 'Koreksi informasi penghuni yang sudah check-out.';
            tenantEditModal.showModal();
        });
    });

    const maintenanceStatus = document.getElementById('maintenanceStatus');
    const maintenanceReportedAt = document.getElementById('maintenanceReportedAt');
    const maintenanceCompletedAt = document.getElementById('maintenanceCompletedAt');
    const maintenanceCompletedField = document.getElementById('maintenanceCompletedField');
    const maintenanceCostLabel = document.getElementById('maintenanceCostLabel');
    const maintenanceSubmit = document.getElementById('maintenanceSubmit');
    const updateMaintenanceMode = () => {
        const historical = maintenanceStatus?.value === 'SELESAI';
        if (maintenanceCompletedField) maintenanceCompletedField.hidden = !historical;
        if (maintenanceCompletedAt) {
            maintenanceCompletedAt.disabled = !historical;
            maintenanceCompletedAt.required = historical;
            maintenanceCompletedAt.min = maintenanceReportedAt?.value || '';
            if (historical && maintenanceReportedAt?.value && (!maintenanceCompletedAt.value || maintenanceCompletedAt.value < maintenanceReportedAt.value)) {
                maintenanceCompletedAt.value = maintenanceReportedAt.value;
            }
        }
        if (maintenanceCostLabel) maintenanceCostLabel.textContent = historical ? 'Biaya aktual' : 'Estimasi biaya';
        if (maintenanceSubmit) maintenanceSubmit.textContent = historical ? 'Simpan histori' : 'Buat tiket';
    };
    maintenanceStatus?.addEventListener('change', updateMaintenanceMode);
    maintenanceReportedAt?.addEventListener('change', updateMaintenanceMode);
    updateMaintenanceMode();

    const expenseEditModal = document.getElementById('expenseEditModal');
    const expenseEditForm = document.getElementById('expenseEditForm');
    const expenseEditCategoryHelp = document.getElementById('expenseEditCategoryHelp');
    document.querySelectorAll('.expense-edit-button').forEach(button => {
        button.addEventListener('click', () => {
            if (!expenseEditModal || !expenseEditForm) return;
            const category = expenseEditForm.elements.namedItem('category');
            const amount = expenseEditForm.elements.namedItem('amount');
            const maintenance = button.dataset.maintenance === '1';
            expenseEditForm.action = button.dataset.action;
            expenseEditForm.elements.namedItem('title').value = button.dataset.title || '';
            category.value = button.dataset.category || '';
            category.disabled = maintenance;
            amount.value = button.dataset.amount || '';
            formatCurrency(amount);
            expenseEditForm.elements.namedItem('spent_at').value = button.dataset.spentAt || '';
            expenseEditForm.elements.namedItem('notes').value = button.dataset.notes || '';
            if (expenseEditCategoryHelp) expenseEditCategoryHelp.textContent = maintenance
                ? 'Kategori Maintenance dijaga otomatis agar tetap sinkron dengan tiket.'
                : '';
            expenseEditModal.showModal();
        });
    });
    expenseEditForm?.addEventListener('submit', () => {
        const category = expenseEditForm.elements.namedItem('category');
        if (category) category.disabled = false;
    });

    const cashflowValue = document.getElementById('cashflowValue');
    document.querySelectorAll('.cashflow-filter').forEach(form => {
        const from = form.querySelector('[name="cashflow_from"]');
        const to = form.querySelector('[name="cashflow_to"]');
        const syncCashflowLimit = () => {
            const start = parseLocalDate(from?.value);
            if (!start || !to) return;
            const latest = new Date(start);
            latest.setFullYear(latest.getFullYear() + 1);
            latest.setDate(latest.getDate() - 1);
            to.min = from.value;
            to.max = toDateValue(latest);
            if (to.value && to.value > to.max) to.value = to.max;
            if (to.value && to.value < to.min) to.value = to.min;
        };
        from?.addEventListener('change', syncCashflowLimit);
        syncCashflowLimit();
    });
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

    document.querySelectorAll('[data-import-row]').forEach(row => {
        const type = row.querySelector('[data-import-type]');
        const updateType = () => {
            row.classList.toggle('is-expense', type?.value === 'EXPENSE');
            row.classList.toggle('is-tenant-history', type?.value === 'TENANT');
        };
        type?.addEventListener('change', updateType);
        updateType();
    });
    document.querySelectorAll('.file-drop input[type="file"]').forEach(input => {
        input.addEventListener('change', () => {
            const label = input.closest('.file-drop')?.querySelector('b');
            if (!label || !input.files?.length) return;
            label.textContent = input.files.length === 1 ? input.files[0].name : `${input.files.length} file dipilih`;
        });
    });

    const toast = document.querySelector('.toast');
    if (toast) setTimeout(() => toast.classList.add('hide'), 4200);
});
