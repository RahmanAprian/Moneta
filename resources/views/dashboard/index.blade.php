<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Dasbor Analitik Uang Elektronik</title>
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
</head>
<body>
<div class="app-shell">
    <aside class="sidebar" id="sidebar">
        <a class="brand" href="{{ route('dashboard') }}"><span class="brand-mark"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 19V9m5 10V5m5 14v-7m5 7V3"/></svg></span><span><b>Moneta</b><small>ANALISIS DATA PUBLIK</small></span></a>
        <div class="nav-caption">RUANG KERJA</div>
        <a class="nav-link active" href="#overview"><span class="nav-icon">▥</span> Ringkasan</a>
        <a class="nav-link" href="#analytics"><span class="nav-icon">⌁</span> Analisis transaksi</a>
        <a class="nav-link" href="#data-table"><span class="nav-icon">▤</span> Data transaksi</a>
        <div class="side-note"><span class="pulse"></span><div><strong>Data publik</strong><small>Terhubung ke berkas BI</small></div></div>
        <div class="sidebar-bottom"><span class="side-avatar">SI</span><div><strong>Riset Sistem Informasi</strong><small>Lingkungan akademik</small></div></div>
    </aside>

    <main class="main-area" id="overview">
        <header class="topbar"><button class="menu-toggle" id="menuToggle" aria-label="Buka navigasi">☰</button><div class="breadcrumb">Analitik <span>/</span> Uang elektronik</div><div class="topbar-right"><span class="live-dot"></span><span>Data resmi · Tabel 5e</span><span class="top-avatar">RA</span></div></header>
        <div class="content-wrap">
            <section class="page-heading">
                <div><div class="eyebrow"><span class="eyebrow-line"></span> DASBOR PENELITIAN</div><h1>Transaksi uang elektronik</h1><p>Jelajahi perkembangan dan komposisi transaksi uang elektronik di Indonesia.</p></div>
                <div class="period-chip"><span class="calendar-icon">▦</span><span><small>CAKUPAN DATA</small><b id="periodAvailable">Memuat…</b><small id="periodSub">—</small></span></div>
            </section>

            <section class="source-strip"><span class="source-icon">i</span><div><strong id="sourceName">Bank Indonesia · Statistik Sistem Pembayaran</strong><span id="sourceDetail">Memeriksa metadata dataset…</span></div><span class="source-updated">Pembaruan <b id="updatedAt">—</b></span></section>

            <section class="filter-panel" aria-label="Filter dashboard">
                <div class="filter-heading"><div><span class="filter-badge">⌕</span><strong>Filter data</strong></div><button class="reset-button" id="resetFilters">↺ <span>Atur ulang</span></button></div>
                <div class="filter-controls">
                    <label>TAMPILAN DATA<select id="granularity"><option value="annual">Tahunan</option><option value="monthly">Bulanan</option></select></label>
                    <label>TAHUN<select id="year"><option value="all">Semua tahun</option></select></label>
                    <label>BULAN<select id="month" disabled><option value="">Semua bulan</option><option value="1">Januari</option><option value="2">Februari</option><option value="3">Maret</option><option value="4">April</option><option value="5">Mei</option><option value="6">Juni</option><option value="7">Juli</option><option value="8">Agustus</option><option value="9">September</option><option value="10">Oktober</option><option value="11">November</option><option value="12">Desember</option></select></label>
                    <label>KATEGORI<select id="category"><option value="">Semua kategori</option><option value="purchase">Belanja</option><option value="top_up">Isi ulang</option><option value="transfer">Transfer</option></select></label>
                    <label class="metric-control">UKURAN GRAFIK<div class="segmented"><button type="button" class="metric-button active" data-metric="value">Nilai</button><button type="button" class="metric-button" data-metric="volume">Volume</button></div></label>
                </div>
            </section>

            <section class="kpi-grid" aria-label="Indikator utama">
                <article class="kpi-card"><div class="kpi-top"><span>Total nilai transaksi</span><span class="kpi-icon icon-violet">↗</span></div><div class="kpi-value" id="kpiValue">—</div><div class="kpi-foot"><span class="kpi-unit" id="kpiValueUnit">Rp Miliar</span><span class="kpi-caption">Periode terpilih</span></div></article>
                <article class="kpi-card"><div class="kpi-top"><span>Total volume transaksi</span><span class="kpi-icon icon-blue">⇄</span></div><div class="kpi-value" id="kpiVolume">—</div><div class="kpi-foot"><span class="kpi-unit" id="kpiVolumeUnit">Ribu Transaksi</span><span class="kpi-caption">Periode terpilih</span></div></article>
                <article class="kpi-card"><div class="kpi-top"><span>Rata-rata per transaksi</span><span class="kpi-icon icon-green">≈</span></div><div class="kpi-value" id="kpiAverage">—</div><div class="kpi-foot"><span class="kpi-unit">Rupiah / transaksi</span><span class="kpi-caption">Nilai ÷ volume × 1 juta</span></div></article>
                <article class="kpi-card"><div class="kpi-top"><span>Kategori nilai terbesar</span><span class="kpi-icon icon-orange">★</span></div><div class="kpi-value category-value" id="kpiTop">—</div><div class="kpi-foot"><span class="kpi-unit">Dari kategori terpilih</span><span class="kpi-caption">Berdasarkan nilai transaksi</span></div></article>
            </section>

            <section class="chart-grid" id="analytics">
                <article class="panel trend-panel"><div class="panel-heading"><div><h2>Tren transaksi</h2><p>Pergerakan transaksi; sumbu rb = ribu, jt = juta satuan.</p></div><span class="chart-tag" id="trendUnit">Rp Miliar</span></div><div class="chart-holder trend-holder"><canvas id="trendChart" aria-label="Grafik tren transaksi" role="img"></canvas><div class="chart-empty hidden" id="trendEmpty">Belum ada data untuk kombinasi filter ini.</div></div></article>
                <article class="panel compare-panel"><div class="panel-heading"><div><h2>Perbandingan kategori</h2><p>Ringkasan per kategori</p></div><span class="chart-tag" id="compareUnit">Nilai</span></div><div class="chart-holder compare-holder"><canvas id="compareChart" aria-label="Grafik perbandingan kategori" role="img"></canvas><div class="chart-empty hidden" id="compareEmpty">Belum ada data.</div></div></article>
                <article class="panel growth-panel"><div class="panel-heading"><div><h2>Pertumbuhan antarperiode</h2><p id="growthSubtitle">Perubahan dari periode sebelumnya</p></div><span class="chart-tag">Persen (%)</span></div><div class="chart-holder growth-holder"><canvas id="growthChart" aria-label="Grafik pertumbuhan transaksi" role="img"></canvas><div class="chart-empty hidden" id="growthEmpty">Pertumbuhan perlu periode pembanding tersedia.</div></div></article>
                <article class="panel proportion-panel"><div class="panel-heading"><div><h2>Proporsi kategori</h2><p>Kontribusi terhadap total <span id="proportionMetric">nilai</span></p></div><span class="chart-tag">Komposisi</span></div><div class="chart-holder proportion-holder"><canvas id="proportionChart" aria-label="Grafik proporsi kategori" role="img"></canvas><div class="chart-empty hidden" id="proportionEmpty">Belum ada data.</div></div></article>
            </section>

            <section class="panel yearly-comparison-panel"><div class="panel-heading"><div><h2>Perbandingan periode sebanding</h2><p>Perbandingan antar tahun hanya memakai bulan yang tersedia pada kedua tahun.</p></div><span class="chart-tag" id="yearComparisonMonths">Agustus</span></div><p class="comparison-note" id="yearComparisonNote">Memeriksa cakupan bulan pembanding…</p><div class="chart-holder year-comparison-holder"><canvas id="yearComparisonChart" aria-label="Perbandingan bulan yang tersedia pada 2025 dan 2026" role="img"></canvas><div class="chart-empty hidden" id="yearComparisonEmpty">Tidak ada bulan sebanding pada filter ini.</div></div></section>

            <section class="panel table-panel" id="data-table"><div class="table-heading"><div><h2>Rincian data transaksi</h2><p>Nilai asli tersimpan; angka tampilan dibulatkan mengikuti format sumber</p></div><label class="search-box"><span>⌕</span><input id="tableSearch" type="search" placeholder="Cari kategori atau periode…" aria-label="Cari data transaksi"></label></div>
                <div class="table-scroll"><table><thead><tr><th><button data-sort="period_start">PERIODE <span>↕</span></button></th><th><button data-sort="category">KATEGORI <span>↕</span></button></th><th class="align-right"><button data-sort="value">NILAI TRANSAKSI <span>↕</span></button></th><th class="align-right"><button data-sort="volume">VOLUME TRANSAKSI <span>↕</span></button></th><th class="align-right">RATA-RATA / TRANSAKSI</th></tr></thead><tbody id="tableBody"><tr><td colspan="5" class="loading-row">Memuat data…</td></tr></tbody></table></div>
                <div class="table-footer"><span id="tableCount">—</span><div class="pagination"><button id="prevPage" aria-label="Halaman sebelumnya" disabled>←</button><span id="pageInfo">—</span><button id="nextPage" aria-label="Halaman berikutnya" disabled>→</button></div></div>
            </section>

            <section class="method-panel"><div class="method-head"><div class="method-icon">⌘</div><div><h2>Tentang data & metodologi</h2><p>Catatan untuk membantu membaca angka dengan tepat.</p></div></div><div class="method-grid"><div><b>Sumber & cakupan</b><p id="methodSource">Bank Indonesia, Tabel 5e, lembar 5e.</p></div><div><b>Data tahunan</b><p id="annualCoverage">—</p></div><div><b>Data bulanan</b><p id="monthlyCoverage">—</p></div><div><b>Kelengkapan data</b><p id="missingCoverage">—</p></div><div><b>Nilai transaksi</b><p>Nilai transaksi tersimpan dengan presisi sumber hingga 12 angka desimal, satuan <strong id="methodValueUnit">Rp Miliar</strong>. Tampilan dibulatkan mengikuti format sumber.</p></div><div><b>Volume transaksi</b><p>Volume transaksi tersimpan dengan presisi sumber hingga 12 angka desimal, satuan <strong id="methodVolumeUnit">Ribu Transaksi</strong>. Bukan nilai rupiah.</p></div><div><b>Rata-rata</b><p>(Nilai × 1.000.000.000) ÷ (volume × 1.000) = rupiah per transaksi. Ditampilkan bila kedua data tersedia dan volume lebih dari nol.</p></div><div><b>Periode tidak tersedia</b><p>Sel kosong pada berkas sumber tidak menjadi angka nol. Data tahunan dan bulanan disajikan terpisah.</p></div><div><b>Kategori</b><p>Kategori pada sumber dipetakan ke Belanja, Isi ulang, dan Transfer Antar Uang Elektronik.</p></div></div></section>
            <footer class="page-footer"><span>Moneta · Dasbor analitik akademik</span><span>Data ditampilkan sebagaimana tersedia pada berkas sumber · <a href="#overview">Kembali ke atas ↑</a></span></footer>
        </div>
    </main>
</div>
<div class="mobile-scrim" id="mobileScrim"></div>
<script src="{{ asset('vendor/chart.umd.js') }}"></script>
<script src="{{ asset('js/dashboard.js') }}"></script>
</body>
</html>
