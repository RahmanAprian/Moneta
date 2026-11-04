(() => {
    'use strict';
    const $ = (selector) => document.querySelector(selector);
    const palette = { purchase: '#705cf6', top_up: '#478af3', transfer: '#1eaa84' };
    const categoryNames = { purchase: 'Belanja', top_up: 'Isi ulang', transfer: 'Transfer' };
    const number = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 });
    const money = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 });
    const state = { metric: 'value', page: 1, search: '', sort: 'period_start', direction: 'desc', response: null, charts: {} };
    let timer = null;
    const format = (n, digits = 2) => n === null || n === undefined || !Number.isFinite(Number(n)) ? '—' : new Intl.NumberFormat('id-ID', { maximumFractionDigits: digits }).format(Number(n));
    const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
    const chartValue = (value) => state.metric === 'value' ? `${money.format(value)} ${state.response?.metadata?.value_unit || ''}` : `${number.format(value)} ${state.response?.metadata?.volume_unit || ''}`;
    const compactAxis = (value) => {
        const n = Number(value);
        if (!Number.isFinite(n)) return '—';
        if (Math.abs(n) >= 1_000_000) return `${format(n / 1_000_000, 1)} jt`;
        if (Math.abs(n) >= 1_000) return `${format(n / 1_000, 0)} rb`;
        return format(n, 0);
    };
    const colorsFor = (keys) => keys.map((key) => palette[key] || '#8e9aab');

    function filterParams() {
        const params = new URLSearchParams();
        const year = $('#year').value;
        const month = $('#month').value;
        const category = $('#category').value;
        params.set('granularity', $('#granularity').value);
        params.set('metric', state.metric);
        if (year !== 'all') params.set('year', year);
        if (month) params.set('month', month);
        if (category) params.set('category', category);
        params.set('page', String(state.page));
        params.set('per_page', '10');
        params.set('search', state.search);
        params.set('sort', state.sort);
        params.set('direction', state.direction);
        return params;
    }

    async function load() {
        const thisRequest = new AbortController();
        if (load.controller) load.controller.abort();
        load.controller = thisRequest;
        $('#tableBody').innerHTML = '<tr><td colspan="5" class="loading-row">Memuat data…</td></tr>';
        try {
            const response = await fetch(`/dashboard/data?${filterParams()}`, { headers: { Accept: 'application/json' }, signal: thisRequest.signal });
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            state.response = await response.json();
            render(state.response);
        } catch (error) {
            if (error.name === 'AbortError') return;
            $('#tableBody').innerHTML = `<tr><td colspan="5" class="empty-row">Data gagal dimuat. Periksa database dan koneksi aplikasi.</td></tr>`;
            console.error('Dashboard API:', error);
        }
    }

    function render(data) {
        renderMetadata(data);
        renderSummary(data);
        renderCharts(data);
        renderTable(data.table);
    }

    function renderMetadata(data) {
        const granularity = $('#granularity').value;
        const years = data.metadata.years?.[granularity] || [];
        const current = $('#year').value;
        const yearSelect = $('#year');
        const wanted = years.map(String);
        if ([...yearSelect.options].slice(1).map((option) => option.value).join(',') !== wanted.join(',')) {
            yearSelect.innerHTML = '<option value="all">Semua tahun</option>' + years.map((year) => `<option value="${escapeHtml(year)}">${escapeHtml(year)}</option>`).join('');
            yearSelect.value = wanted.includes(current) ? current : 'all';
        }
        const monthSelect = $('#month');
        monthSelect.disabled = $('#granularity').value !== 'monthly';
        if (monthSelect.disabled) monthSelect.value = '';
        const coverage = data.metadata.coverage || {};
        const annualFirst = coverage.annual?.first?.slice(0, 4);
        const annualLast = coverage.annual?.last?.slice(0, 4);
        const monthLabel = (iso) => iso ? new Date(`${iso}T00:00:00`).toLocaleDateString('id-ID', { month: 'long', year: 'numeric' }) : 'belum tersedia';
        $('#periodAvailable').textContent = annualFirst && annualLast ? `Data tahunan: ${annualFirst}–${annualLast}` : 'Data tahunan belum tersedia';
        $('#periodSub').textContent = coverage.monthly?.first && coverage.monthly?.last ? `Data bulanan: ${monthLabel(coverage.monthly.first)}–${monthLabel(coverage.monthly.last)}` : 'Data bulanan belum tersedia';
        $('#growthSubtitle').textContent = granularity === 'annual'
            ? 'Perubahan tahun-ke-tahun; 2020 tidak memiliki periode sebelumnya.'
            : 'Perubahan bulanan berurutan; setiap angka dihitung dari nilai sumber berpresisi penuh.';
        $('#sourceName').textContent = data.metadata.source || 'Sumber belum tercatat';
        const sourceFileDisplay = (data.metadata.source_file || 'Nama berkas tidak tercatat').replace(/\bsheet\b/gi, 'lembar');
        const sourceInfo = [sourceFileDisplay, annualFirst && annualLast ? `Tahunan ${annualFirst}–${annualLast}` : null, coverage.monthly?.first && coverage.monthly?.last ? `Bulanan ${monthLabel(coverage.monthly.first)}–${monthLabel(coverage.monthly.last)}` : null, data.metadata.retrieved_at ? `diambil ${new Date(data.metadata.retrieved_at).toLocaleDateString('id-ID')}` : 'tanggal pengambilan BI tidak tercantum'].filter(Boolean).join(' · ');
        $('#sourceDetail').textContent = sourceInfo;
        $('#updatedAt').textContent = data.metadata.imported_at ? new Date(data.metadata.imported_at).toLocaleDateString('id-ID') : 'belum ada impor';
        $('#methodSource').textContent = `${data.metadata.source || 'Sumber belum tersedia'}; ${sourceFileDisplay}. Tanggal pengambilan sumber tidak tercantum/terverifikasi.`;
        $('#annualCoverage').textContent = `2020–2025; ${coverage.annual?.count ?? 0} observasi (${(coverage.annual?.count ?? 0) / 3 || 0} per kategori).`;
        $('#monthlyCoverage').textContent = coverage.monthly?.first && coverage.monthly?.last
            ? `${monthLabel(coverage.monthly.first)}–${monthLabel(coverage.monthly.last)}; ${coverage.monthly.count} observasi (${coverage.monthly.count / 3} per kategori).`
            : 'Data bulanan belum tersedia.';
        $('#missingCoverage').textContent = data.metadata.study_window?.monthly_note || 'Sel kosong tidak dianggap nol.';
        $('#methodValueUnit').textContent = data.metadata.value_unit || 'tidak tercatat';
        $('#methodVolumeUnit').textContent = data.metadata.volume_unit || 'tidak tercatat';
        $('#yearComparisonNote').textContent = granularity === 'monthly'
            ? data.year_comparison.note
            : 'Perbandingan ini hanya memakai data bulanan dan tidak dicampur dengan nilai tahunan. Pilih tampilan Bulanan untuk melihatnya.';
        const comparisonMonths = data.year_comparison.month_names;
        $('#yearComparisonMonths').textContent = granularity === 'monthly'
            ? (comparisonMonths.length > 2 ? `${comparisonMonths[0]}–${comparisonMonths[comparisonMonths.length - 1]}` : (comparisonMonths.length ? comparisonMonths.join(', ') : 'Tidak ada bulan bersama'))
            : 'Mode bulanan saja';
    }

    function renderSummary(data) {
        const summary = data.summary;
        $('#kpiValue').textContent = summary.total_value === null ? '—' : format(summary.total_value, 0);
        $('#kpiVolume').textContent = summary.total_volume === null ? '—' : format(summary.total_volume, 0);
        $('#kpiAverage').textContent = summary.average_transaction_rupiah === null ? '—' : `Rp ${format(summary.average_transaction_rupiah)}`;
        $('#kpiTop').textContent = summary.top_category || '—';
        $('#kpiValueUnit').textContent = data.metadata.value_unit || 'unit tidak diketahui';
        $('#kpiVolumeUnit').textContent = data.metadata.volume_unit || 'unit tidak diketahui';
        const unit = state.metric === 'value' ? data.metadata.value_unit : data.metadata.volume_unit;
        $('#trendUnit').textContent = unit || 'Satuan tidak tercatat';
        $('#compareUnit').textContent = unit || (state.metric === 'value' ? 'Nilai' : 'Volume');
        $('#proportionMetric').textContent = state.metric === 'value' ? 'nilai' : 'volume';
    }

    function showEmpty(canvasId, emptyId, show) {
        $(canvasId).classList.toggle('hidden', show);
        $(emptyId).classList.toggle('hidden', !show);
    }

    function makeChart(key, canvas, config) {
        if (typeof Chart === 'undefined') {
            showEmpty(`#${canvas}`, key.replace('Chart', 'Empty'), true);
            $(key.replace('Chart', 'Empty')).textContent = 'Chart.js tidak termuat. Periksa koneksi internet.';
            return null;
        }
        if (state.charts[key]) state.charts[key].destroy();
        return state.charts[key] = new Chart($(canvas), config);
    }

    const axisOptions = { grid: { color: '#f0f2f5', drawBorder: false }, border: { display: false }, ticks: { color: '#96a0ad', font: { family: 'DM Sans', size: 9 }, maxTicksLimit: 6 } };
    const tooltipOptions = { backgroundColor: '#202c3b', padding: 10, titleFont: { family: 'DM Sans', size: 10, weight: '600' }, bodyFont: { family: 'DM Sans', size: 10 }, displayColors: true, boxPadding: 4 };

    function renderCharts(data) {
        const categories = data.categories || [];
        const keys = categories.map((item) => item.slug);
        const labels = data.trend.map((item) => {
            const [year, month] = item.period.split('-');
            return month && month !== '01' || $('#granularity').value === 'monthly'
                ? new Date(Number(year), Number(month || 1) - 1, 1).toLocaleDateString('id-ID', { month: 'short', year: '2-digit' })
                : year;
        });
        const hasTrend = data.trend.some((point) => keys.some((key) => point.values[key]?.[state.metric] !== null && point.values[key]?.[state.metric] !== undefined));
        showEmpty('#trendChart', '#trendEmpty', !hasTrend);
        if (hasTrend) {
            makeChart('trendChart', '#trendChart', {
                type: 'line', data: { labels, datasets: keys.map((key) => ({ label: categoryNames[key] || key, data: data.trend.map((point) => point.values[key]?.[state.metric] ?? null), borderColor: palette[key], backgroundColor: palette[key], borderWidth: 2, pointRadius: 2.5, pointHoverRadius: 5, tension: .32, spanGaps: false })) },
                options: { responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false }, plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, pointStyle: 'circle', boxWidth: 7, boxHeight: 7, padding: 16, color: '#758191', font: { family: 'DM Sans', size: 9 } } }, tooltip: { ...tooltipOptions, callbacks: { label: (ctx) => `${ctx.dataset.label}: ${ctx.parsed.y === null ? 'tidak tersedia' : chartValue(ctx.parsed.y)}` } } }, scales: { x: { ...axisOptions, grid: { display: false } }, y: { ...axisOptions, ticks: { ...axisOptions.ticks, callback: (value) => compactAxis(value) } } } }
            });
        }

        const compareValues = categories.map((item) => item[state.metric]);
        const hasCompare = compareValues.some((value) => value !== null && value !== undefined);
        showEmpty('#compareChart', '#compareEmpty', !hasCompare);
        if (hasCompare) {
            makeChart('compareChart', '#compareChart', {
                type: 'bar', data: { labels: categories.map((item) => item.name), datasets: [{ data: compareValues, backgroundColor: colorsFor(keys), borderRadius: 5, maxBarThickness: 30 }] },
                options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { ...tooltipOptions, callbacks: { label: (ctx) => chartValue(ctx.parsed.x) } } }, scales: { x: { ...axisOptions, ticks: { ...axisOptions.ticks, callback: (value) => compactAxis(value) } }, y: { ...axisOptions, grid: { display: false }, ticks: { ...axisOptions.ticks, color: '#657180' } } } }
            });
        }

        const growthForMetric = data.growth.filter((point) => point.growth !== null && keys.includes(point.category));
        const growthPeriods = [...new Set(growthForMetric.map((point) => point.period))].sort();
        const hasGrowth = growthForMetric.length > 0;
        showEmpty('#growthChart', '#growthEmpty', !hasGrowth);
        if (hasGrowth) {
            makeChart('growthChart', '#growthChart', {
                type: 'line', data: { labels: growthPeriods.map((period) => { const [y, m] = period.split('-'); return $('#granularity').value === 'monthly' ? new Date(+y, +m - 1, 1).toLocaleDateString('id-ID', { month: 'short', year: '2-digit' }) : y; }), datasets: keys.map((key) => ({ label: categoryNames[key] || key, data: growthPeriods.map((period) => data.growth.find((item) => item.period === period && item.category === key)?.growth ?? null), borderColor: palette[key], backgroundColor: palette[key], borderWidth: 2, pointRadius: 2.5, tension: .25, spanGaps: false })) },
                options: { responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false }, plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, pointStyle: 'circle', boxWidth: 7, padding: 14, color: '#758191', font: { family: 'DM Sans', size: 9 } } }, tooltip: { ...tooltipOptions, callbacks: { label: (ctx) => `${ctx.dataset.label}: ${ctx.parsed.y === null ? 'tidak tersedia' : `${format(ctx.parsed.y)}%`}` } } }, scales: { x: { ...axisOptions, grid: { display: false } }, y: { ...axisOptions, ticks: { ...axisOptions.ticks, callback: (value) => `${format(value, 0)}%` } } } }
            });
        }

        const pieValues = categories.map((item) => item[state.metric]);
        const hasPie = pieValues.some((value) => value !== null && value !== undefined && value > 0);
        showEmpty('#proportionChart', '#proportionEmpty', !hasPie);
        if (hasPie) {
            makeChart('proportionChart', '#proportionChart', {
                type: 'doughnut', data: { labels: categories.map((item) => item.name), datasets: [{ data: pieValues, backgroundColor: colorsFor(keys), borderWidth: 0, hoverOffset: 4 }] },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '67%',
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                generateLabels: (chart) => {
                                    const values = chart.data.datasets[0].data;
                                    const total = values.reduce((sum, value) => sum + (Number(value) || 0), 0);
                                    return chart.data.labels.map((label, index) => ({
                                        text: `${label} (${total ? format(values[index] / total * 100, 1) : '0'}%)`,
                                        fillStyle: chart.data.datasets[0].backgroundColor[index],
                                        strokeStyle: chart.data.datasets[0].backgroundColor[index],
                                        lineWidth: 0,
                                        hidden: false,
                                        index,
                                    }));
                                },
                                usePointStyle: true,
                                pointStyle: 'circle',
                                boxWidth: 7,
                                boxHeight: 7,
                                padding: 14,
                                color: '#758191',
                                font: { family: 'DM Sans', size: 9 },
                            },
                        },
                        tooltip: {
                            ...tooltipOptions,
                            callbacks: {
                                label: (ctx) => {
                                    const total = ctx.dataset.data.reduce((sum, value) => sum + (Number(value) || 0), 0);
                                    const value = Number(ctx.raw) || 0;
                                    return `${ctx.label}: ${format(value, 0)} (${total ? format(value / total * 100, 1) : '0'}%)`;
                                },
                            },
                        },
                    },
                },
            });
        }
        renderYearComparison(data);
    }

    function renderYearComparison(data) {
        const comparison = data.year_comparison;
        const categories = comparison.categories || [];
        const hasData = $('#granularity').value === 'monthly' && comparison.months.length > 0 && categories.some((category) => category.data_2025[state.metric] !== null && category.data_2026[state.metric] !== null);
        showEmpty('#yearComparisonChart', '#yearComparisonEmpty', !hasData);
        if (!hasData) return;
        makeChart('yearComparisonChart', '#yearComparisonChart', {
            type: 'bar',
            data: {
                labels: categories.map((category) => category.name),
                datasets: [
                    { label: '2025', data: categories.map((category) => category.data_2025[state.metric]), backgroundColor: '#a79bf9', borderRadius: 4, maxBarThickness: 34 },
                    { label: '2026', data: categories.map((category) => category.data_2026[state.metric]), backgroundColor: '#4688f5', borderRadius: 4, maxBarThickness: 34 },
                ],
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { usePointStyle: true, pointStyle: 'circle', boxWidth: 8, padding: 15, color: '#758191', font: { family: 'DM Sans', size: 9 } } },
                    tooltip: { ...tooltipOptions, callbacks: { label: (ctx) => `${ctx.dataset.label}: ${ctx.parsed.y === null ? 'tidak tersedia' : chartValue(ctx.parsed.y)}` } },
                },
                scales: { x: { ...axisOptions, grid: { display: false } }, y: { ...axisOptions, ticks: { ...axisOptions.ticks, callback: (value) => compactAxis(value) } } },
            },
        });
    }

    function renderTable(table) {
        const rows = table.data || [];
        if (!rows.length) {
            $('#tableBody').innerHTML = '<tr><td colspan="5" class="empty-row">Tidak ada data yang cocok dengan filter ini.</td></tr>';
        } else {
            $('#tableBody').innerHTML = rows.map((row) => {
                const key = Object.keys(categoryNames).find((slug) => categoryNames[slug] === row.category) || 'purchase';
                return `<tr><td class="period-cell">${escapeHtml(row.period)}<span class="granularity-tag">${row.granularity === 'annual' ? 'Tahunan' : 'Bulanan'}</span></td><td><span class="category-cell"><i class="category-dot ${key}"></i>${escapeHtml(row.category)}</span></td><td class="number-cell">${row.value === null ? '—' : `${format(row.value, 0)} <span class="kpi-unit">${escapeHtml(row.value_unit)}</span>`}</td><td class="number-cell">${row.volume === null ? '—' : `${format(row.volume, 0)} <span class="kpi-unit">${escapeHtml(row.volume_unit)}</span>`}</td><td class="number-cell">${row.average_transaction_rupiah === null ? '—' : `Rp ${format(row.average_transaction_rupiah)}`}</td></tr>`;
            }).join('');
        }
        $('#tableCount').textContent = `${table.total} baris data`;
        $('#pageInfo').textContent = `${table.current_page} / ${table.last_page}`;
        $('#prevPage').disabled = table.current_page <= 1;
        $('#nextPage').disabled = table.current_page >= table.last_page;
    }

    ['granularity', 'year', 'month', 'category'].forEach((id) => $(`#${id}`).addEventListener('change', () => {
        state.page = 1;
        if (id === 'granularity') { $('#year').value = 'all'; if ($('#granularity').value === 'annual') $('#month').value = ''; }
        load();
    }));
    document.querySelectorAll('.metric-button').forEach((button) => button.addEventListener('click', () => {
        state.metric = button.dataset.metric;
        document.querySelectorAll('.metric-button').forEach((item) => item.classList.toggle('active', item === button));
        load();
    }));
    $('#resetFilters').addEventListener('click', () => {
        $('#granularity').value = 'annual'; $('#year').value = 'all'; $('#month').value = ''; $('#category').value = '';
        state.metric = 'value'; state.page = 1; state.search = ''; state.sort = 'period_start'; state.direction = 'desc'; $('#tableSearch').value = '';
        document.querySelectorAll('.metric-button').forEach((item) => item.classList.toggle('active', item.dataset.metric === 'value'));
        load();
    });
    $('#tableSearch').addEventListener('input', (event) => { clearTimeout(timer); timer = setTimeout(() => { state.search = event.target.value.trim(); state.page = 1; load(); }, 250); });
    $('#prevPage').addEventListener('click', () => { if (state.response?.table.current_page > 1) { state.page--; load(); } });
    $('#nextPage').addEventListener('click', () => { if (state.response?.table.current_page < state.response?.table.last_page) { state.page++; load(); } });
    document.querySelectorAll('[data-sort]').forEach((button) => button.addEventListener('click', () => {
        if (state.sort === button.dataset.sort) state.direction = state.direction === 'asc' ? 'desc' : 'asc';
        else { state.sort = button.dataset.sort; state.direction = 'desc'; }
        state.page = 1; load();
    }));
    $('#menuToggle').addEventListener('click', () => { $('#sidebar').classList.toggle('open'); $('#mobileScrim').classList.toggle('show'); });
    $('#mobileScrim').addEventListener('click', () => { $('#sidebar').classList.remove('open'); $('#mobileScrim').classList.remove('show'); });
    document.querySelectorAll('.nav-link').forEach((link) => link.addEventListener('click', () => { $('#sidebar').classList.remove('open'); $('#mobileScrim').classList.remove('show'); }));
    load();
})();
