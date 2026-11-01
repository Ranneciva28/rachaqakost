(() => {
    const form = document.querySelector('[data-simulation-form]');
    if (!form) return;

    const facts = JSON.parse(document.getElementById('simulationFacts')?.textContent || '{}');
    const costs = JSON.parse(document.getElementById('simulationCosts')?.textContent || '[]');
    const range = form.querySelector('[data-occupancy-range]');
    const occupancy = form.querySelector('[data-occupancy-number]');
    const rate = form.querySelector('[data-simulation-daily-rate]');
    const commission = form.querySelector('[data-simulation-commission]');
    const days = form.querySelector('[data-simulation-days]');
    const money = new Intl.NumberFormat('id-ID', {style: 'currency', currency: 'IDR', maximumFractionDigits: 0});

    const numberValue = (input, fallback = 0) => {
        const raw = input?.matches?.('[data-currency]')
            ? String(input.value ?? '').replace(/\D/g, '')
            : String(input?.value ?? '').replace(',', '.');
        const value = Number(raw);
        return Number.isFinite(value) ? value : fallback;
    };
    const clamp = (value, min, max) => Math.min(max, Math.max(min, value));
    const costTotal = (type, scenario) => costs
        .filter(cost => cost.type === type && [scenario, 'BOTH'].includes(cost.applies_to))
        .reduce((sum, cost) => sum + Number(cost.amount || 0), 0);
    const scenario = (gross, ota, revenue, operating, investment) => {
        const profit = revenue - operating;
        return {
            gross_revenue: gross,
            ota_fee: ota,
            net_revenue: revenue,
            operating_cost: operating,
            investment,
            net_profit: profit,
            roi: investment > 0 ? profit * 12 / investment * 100 : null,
            payback_months: investment > 0 && profit > 0 ? investment / profit : null,
        };
    };
    const calculate = () => {
        const occupied = clamp(numberValue(occupancy), 0, 100) / 100;
        const otaRate = clamp(numberValue(commission), 0, 100) / 100;
        const projectionDays = Math.max(1, numberValue(days, 30));
        const dailyRate = String(rate?.value ?? '').trim() === ''
            ? Number(facts.actual_daily_rate || 0)
            : numberValue(rate, Number(facts.actual_daily_rate || 0));
        const fullOperating = costTotal('OPERATIONAL', 'FULL_MONTHLY');
        const hybridOperating = costTotal('OPERATIONAL', 'HYBRID');
        const fullInvestment = costTotal('INVESTMENT', 'FULL_MONTHLY');
        const hybridInvestment = costTotal('INVESTMENT', 'HYBRID');
        const fullGross = Number(facts.full_monthly_revenue || 0);
        const dailyGross = Number(facts.daily_rooms || 0) * dailyRate * projectionDays * occupied;
        const otaFee = dailyGross * otaRate;
        const hybridGross = dailyGross + Number(facts.hybrid_monthly_revenue || 0);
        const hybridRevenue = hybridGross - otaFee;
        const full = scenario(fullGross, 0, fullGross, fullOperating, fullInvestment);
        const hybrid = scenario(hybridGross, otaFee, hybridRevenue, hybridOperating, hybridInvestment);
        const dailyNetAtFull = Number(facts.daily_rooms || 0) * dailyRate * projectionDays * (1 - otaRate);
        return {
            full, hybrid,
            difference: hybrid.net_profit - full.net_profit,
            full_break_even_occupancy: fullGross > 0 ? fullOperating / fullGross * 100 : null,
            hybrid_break_even_occupancy: dailyNetAtFull > 0 ? Math.max(0, hybridOperating - Number(facts.hybrid_monthly_revenue || 0)) / dailyNetAtFull * 100 : null,
            hybrid_parity_occupancy: dailyNetAtFull > 0 ? Math.max(0, full.net_profit + hybridOperating - Number(facts.hybrid_monthly_revenue || 0)) / dailyNetAtFull * 100 : null,
        };
    };
    const get = (object, path) => path.split('.').reduce((value, key) => value?.[key], object);
    const format = (key, value) => {
        if (key === 'difference') return money.format(Math.abs(value || 0));
        if (key.endsWith('payback_months')) return value === null ? '—' : `${new Intl.NumberFormat('id-ID', {maximumFractionDigits: 1}).format(value)} bulan`;
        if (key.includes('occupancy') || key.endsWith('.roi')) return value === null ? '—' : `${new Intl.NumberFormat('id-ID', {maximumFractionDigits: 1}).format(value)}%`;
        return money.format(value || 0);
    };
    const render = () => {
        const result = calculate();
        document.querySelectorAll('[data-metric]').forEach(node => {
            const key = node.dataset.metric;
            node.textContent = format(key, get(result, key));
            node.classList.toggle('negative', Number(get(result, key)) < 0 && key !== 'difference');
        });
        const label = document.querySelector('[data-difference-label]');
        if (label) label.textContent = result.difference >= 0 ? 'Hybrid lebih menguntungkan' : 'Full Bulanan lebih menguntungkan';
    };

    range?.addEventListener('input', () => { occupancy.value = range.value; render(); });
    occupancy?.addEventListener('input', () => { range.value = occupancy.value; render(); });
    [rate, commission, days].forEach(input => input?.addEventListener('input', render));
    render();
})();
