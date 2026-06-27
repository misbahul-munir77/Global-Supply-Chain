<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — Global Supply Chain Monitor</title>
    <meta name="description" content="Platform monitoring risiko rantai pasok global real-time">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">

    <style>
        /* Layout 3 kolom: sidebar (fixed) + konten tengah + panel kanan */
        .main-three-col {
            display: flex;
            min-height: calc(100vh - 56px);
            margin-left: var(--sidebar-w); /* geser konten karena sidebar fixed */
            transition: margin-left 0.28s ease;
        }

        /* Saat sidebar ditutup, konten mundur ke kiri */
        .main-three-col.sidebar-closed {
            margin-left: 0;
        }

        .main-content {
            flex: 1;
            padding: 22px;
            overflow-y: auto;
            min-width: 0;
        }

        .right-panel {
            width: 288px;
            min-width: 288px;
            padding: 20px 14px;
            overflow-y: auto;
            background: #f0f4f8;
            border-left: 1px solid #dde4ed;
        }

        /* Grid layout */
        .stat-grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
        .stat-grid-2 { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; }
        .port-grid   { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 12px; }

        .loading-indicator {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 11px;
            color: #6b7c93;
        }

        /* Tab filter berita */
        .news-tabs { display: flex; gap: 4px; margin-bottom: 10px; flex-wrap: wrap; }
        .news-tab {
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
            cursor: pointer;
            border: 1px solid rgba(255,255,255,0.15);
            color: rgba(255,255,255,0.5);
            transition: all 0.2s;
            background: transparent;
        }
        .news-tab.active { background: #1a73e8; color: #fff; border-color: #1a73e8; }

        @keyframes pulse-dot {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.4; }
        }
        .status-dot.normal { animation: pulse-dot 2s infinite; }

        /* Popup peta */
        .leaflet-popup-content-wrapper {
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            font-family: 'Inter', sans-serif;
            font-size: 13px;
        }
        .leaflet-popup-content { margin: 12px 16px; }
        .popup-title { font-weight: 700; font-size: 14px; color: #1a2332; margin-bottom: 8px; }
        .popup-row {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            font-size: 12px;
            color: #6b7c93;
            margin-bottom: 4px;
        }
        .popup-row span:last-child { color: #1a2332; font-weight: 600; }
        .popup-btn {
            display: block;
            margin-top: 10px;
            background: #1a73e8;
            color: #fff;
            text-align: center;
            border-radius: 6px;
            padding: 6px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            width: 100%;
            transition: background 0.2s;
        }
        .popup-btn:hover { background: #155db2; }

        /* Risk breakdown */
        .risk-breakdown-item { margin-bottom: 10px; }
        .risk-breakdown-item .label-row {
            display: flex;
            justify-content: space-between;
            font-size: 11px;
            color: #6b7c93;
            margin-bottom: 4px;
        }
        .risk-breakdown-item .label-row span:last-child { font-weight: 700; color: #1a2332; }

        /* Strip prakiraan cuaca 7 hari */
        .forecast-strip { display: grid; grid-template-columns: repeat(7, 1fr); gap: 6px; }
        .forecast-day {
            background: #f7faff;
            border: 1px solid #e0eaf6;
            border-radius: 8px;
            padding: 8px 4px;
            text-align: center;
        }
        .forecast-day .day-label { font-size: 10px; color: #6b7c93; font-weight: 600; margin-bottom: 4px; }
        .forecast-day .day-temp  { font-size: 13px; font-weight: 700; color: #1a2332; }
        .forecast-day .day-rain  { font-size: 10px; color: #1a73e8; margin-top: 2px; }
        .forecast-day.today { background: #e3f0ff; border-color: #1a73e8; }

        /* Overlay gelap saat sidebar terbuka di layar kecil */
        .sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.4);
            z-index: 490;
        }
        .sidebar-overlay.show { display: block; }

        @media (max-width: 768px) {
            .right-panel { display: none; }
            .main-three-col { margin-left: 0 !important; }
        }
    </style>
</head>
<body>

{{-- ─── NAVBAR ─── --}}
<nav class="navbar-supply">
    <div style="display:flex; align-items:center; gap:12px;">
        {{-- Tombol toggle sidebar --}}
        <button class="sidebar-toggle-btn" id="sidebarToggleBtn" onclick="toggleSidebar()" title="Buka/tutup sidebar">
            <i class="bi bi-list" id="toggleIcon"></i>
        </button>
        <a class="navbar-brand-supply" href="#">
            <div class="brand-icon">
                <i class="bi bi-box-seam-fill" style="color:#fff; font-size:16px;"></i>
            </div>
            Global Supply Chain Monitor
        </a>
    </div>

    <div class="navbar-right">
        {{-- Kurs USD/IDR — diisi JavaScript --}}
        <div class="exchange-badge" id="navExchangeBadge">
            <i class="bi bi-currency-exchange"></i>
            <span id="navRateLabel">USD/IDR:</span>
            <span class="rate-value" id="navUsdRate">
                <span class="spinner-supply" style="width:12px; height:12px; border-width:1.5px;"></span>
            </span>
            <span id="navUsdChange"></span>
        </div>
        <span style="color: rgba(255,255,255,0.5); font-size: 13px;">
            Halo, <strong style="color:#fff;">{{ Auth::user()->name }}</strong>
        </span>
        <a href="/logout" class="btn-logout" onclick="return confirm('Yakin ingin keluar?')">
            <i class="bi bi-box-arrow-right me-1"></i>Logout
        </a>
    </div>
</nav>

{{-- Overlay saat sidebar terbuka di layar kecil --}}
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

{{-- ─── LAYOUT UTAMA ─── --}}
<div class="main-three-col" id="mainLayout">

    {{-- ─── SIDEBAR KIRI (fixed) ─── --}}
    <div class="sidebar" id="sidebar">

        <div class="sidebar-brand-box">
            <h6>Rantai Pasok</h6>
            <small>Monitor global</small>
        </div>

        <ul class="sidebar-nav">
            <li>
                <a href="#section-map" class="active" id="nav-map" onclick="scrollToSection('section-map', this)">
                    <i class="bi bi-globe2" style="color: #00c6ff;"></i> Peta Global
                </a>
            </li>
            <li>
                <a href="#section-risk" id="nav-risk" onclick="scrollToSection('section-risk', this)">
                    <i class="bi bi-exclamation-triangle-fill" style="color: #ff9100;"></i> Analisis Risiko
                </a>
            </li>
            <li>
                <a href="#section-ports" id="nav-ports" onclick="scrollToSection('section-ports', this)">
                    <i class="bi bi-anchor" style="color: #00e5ff;"></i> Data Pelabuhan
                </a>
            </li>
            <li>
                <a href="#section-weather" id="nav-weather" onclick="scrollToSection('section-weather', this)">
                    <i class="bi bi-cloud-sun-fill" style="color: #ffc107;"></i> Cuaca
                </a>
            </li>
            <li>
                <a href="#section-economy" id="nav-economy" onclick="scrollToSection('section-economy', this)">
                    <i class="bi bi-bar-chart-line-fill" style="color: #00c853;"></i> Laporan Ekonomi
                </a>
            </li>
        </ul>

        <div class="sidebar-section-title">Berita Terkini</div>

        <div class="news-tabs">
            <button class="news-tab active" onclick="loadNews('all', this)">Semua</button>
            <button class="news-tab" onclick="loadNews('economy', this)">Ekonomi</button>
            <button class="news-tab" onclick="loadNews('logistics', this)">Logistik</button>
            <button class="news-tab" onclick="loadNews('geopolitics', this)">Geopolitik</button>
        </div>

        <div id="newsList">
            <div class="skeleton skeleton-text" style="height:50px; margin-bottom:8px;"></div>
            <div class="skeleton skeleton-text" style="height:50px; margin-bottom:8px;"></div>
            <div class="skeleton skeleton-text" style="height:50px;"></div>
        </div>

        <div style="flex:1;"></div>

        <div class="sidebar-user">
            <div class="avatar">{{ strtoupper(substr(Auth::user()->name, 0, 1)) }}</div>
            <div class="user-info">
                <p>{{ Auth::user()->name }}</p>
                <small>{{ ucfirst(Auth::user()->role) }} Akses</small>
            </div>
        </div>

    </div>{{-- /sidebar --}}


    {{-- ─── KONTEN TENGAH ─── --}}
    <div class="main-content" id="mainContent">

        <div class="page-header d-flex justify-content-between align-items-start">
            <div>
                <h4>Dashboard Monitoring Real-Time</h4>
                <small>Update terakhir: <span id="lastUpdate" style="color:#1a73e8; font-weight:600;">Memuat...</span></small>
            </div>
            <button onclick="refreshAll()"
                style="background:#fff; border:1px solid #dde4ed; border-radius:8px;
                       padding:7px 14px; font-size:12px; font-weight:600; color:#1a73e8;
                       cursor:pointer; display:flex; align-items:center; gap:6px; transition:all 0.2s;"
                onmouseover="this.style.background='#e3f0ff'"
                onmouseout="this.style.background='#fff'">
                <i class="bi bi-arrow-clockwise" id="refreshIcon"></i> Refresh Data
            </button>
        </div>

        {{-- ─── PETA LEAFLET ─── --}}
        <div class="supply-card fade-in" id="section-map">
            <div class="card-head">
                <h6><i class="bi bi-map-fill me-2" style="color:#1a73e8;"></i>Peta Jalur Logistik Global</h6>
                <div style="display:flex; align-items:center; gap:16px;">
                    <span style="font-size:11px; display:flex; align-items:center; gap:5px; color:#6b7c93;">
                        <span class="status-dot normal"></span> Normal
                    </span>
                    <span style="font-size:11px; display:flex; align-items:center; gap:5px; color:#6b7c93;">
                        <span class="status-dot waspada"></span> Waspada
                    </span>
                    <span style="font-size:11px; display:flex; align-items:center; gap:5px; color:#6b7c93;">
                        <span class="status-dot gangguan"></span> Gangguan
                    </span>
                    <div class="loading-indicator" id="mapLoader">
                        <span class="spinner-supply" style="width:14px; height:14px; border-width:2px;"></span> Memuat...
                    </div>
                </div>
            </div>
            <div id="map"></div>
        </div>

        {{-- ─── ANALISIS RISIKO ─── --}}
        <div class="supply-card fade-in" id="section-risk">
            <div class="card-head">
                <h6><i class="bi bi-shield-exclamation me-2" style="color:#ff9100;"></i>Analisis Risiko Rantai Pasok</h6>
                <div class="loading-indicator" id="riskLoader">
                    <span class="spinner-supply" style="width:14px; height:14px; border-width:2px;"></span>
                </div>
            </div>
            <div class="card-body-supply">
                <div style="display:flex; align-items:center; gap:20px; margin-bottom:20px;">
                    <div class="risk-score-circle medium" id="riskCircle">--</div>
                    <div>
                        <div style="font-size:16px; font-weight:700; color:#1a2332; margin-bottom:4px;">
                            Skor Risiko: <span id="riskScoreLabel">Pilih negara</span>
                        </div>
                        <div style="font-size:12px; color:#6b7c93;" id="riskLevelLabel">
                            Data akan muncul setelah negara dipilih
                        </div>
                    </div>
                </div>
                <div class="stat-grid-3" id="riskBreakdown">
                    <div class="skeleton" style="height:70px; border-radius:10px;"></div>
                    <div class="skeleton" style="height:70px; border-radius:10px;"></div>
                    <div class="skeleton" style="height:70px; border-radius:10px;"></div>
                </div>
            </div>
        </div>

        {{-- ─── DATA PELABUHAN ─── --}}
        <div class="supply-card fade-in" id="section-ports">
            <div class="card-head">
                <h6><i class="bi bi-anchor me-2" style="color:#1a73e8;"></i>Port Logistics Data</h6>
                <div style="display:flex; align-items:center; gap:10px;">
                    <span class="region-badge" id="portRegionBadge">GLOBAL PORTS</span>
                    <div class="loading-indicator" id="portLoader">
                        <span class="spinner-supply" style="width:14px; height:14px; border-width:2px;"></span>
                    </div>
                </div>
            </div>
            <div class="card-body-supply">
                <div class="port-grid" id="portGrid">
                    @for($i = 0; $i < 6; $i++)
                    <div class="skeleton" style="height:100px; border-radius:10px;"></div>
                    @endfor
                </div>
            </div>
        </div>

        {{-- ─── CUACA & LINGKUNGAN ─── --}}
        <div class="supply-card fade-in" id="section-weather">
            <div class="card-head">
                <h6>
                    <i class="bi bi-cloud-sun-fill me-2" style="color:#ff9100;"></i>
                    OpenMeteo Environmental Trends —
                    <span id="weatherLocationLabel" style="font-weight:400; color:#6b7c93; font-size:12px;">Memuat...</span>
                </h6>
                <div class="loading-indicator" id="weatherLoader">
                    <span class="spinner-supply" style="width:14px; height:14px; border-width:2px;"></span>
                </div>
            </div>
            <div class="card-body-supply">

                <div class="forecast-strip" id="forecastStrip">
                    @for($i = 0; $i < 7; $i++)
                    <div class="skeleton" style="height:72px; border-radius:8px;"></div>
                    @endfor
                </div>

                <div style="margin-top:16px;">
                    <div style="font-size:12px; font-weight:700; color:#6b7c93; text-transform:uppercase;
                                letter-spacing:0.5px; margin-bottom:8px;">Grafik Suhu 7 Hari (°C)</div>
                    <div class="chart-wrapper">
                        <canvas id="tempChart"></canvas>
                    </div>
                </div>

                <div style="margin-top:16px;">
                    <div style="font-size:12px; font-weight:700; color:#6b7c93; text-transform:uppercase;
                                letter-spacing:0.5px; margin-bottom:8px;">Curah Hujan 7 Hari (mm)</div>
                    <div class="chart-wrapper" style="height:120px;">
                        <canvas id="rainChart"></canvas>
                    </div>
                </div>

                <div style="margin-top:16px; padding:12px 14px; background:#f7faff;
                            border:1px solid #e0eaf6; border-radius:10px;
                            display:flex; align-items:center; gap:14px;">
                    <div style="font-size:28px;" id="weatherRiskIcon">🌤️</div>
                    <div>
                        <div style="font-size:13px; font-weight:700; color:#1a2332;" id="weatherRiskText">
                            Menganalisis kondisi cuaca...
                        </div>
                        <div style="font-size:11px; color:#6b7c93; margin-top:2px;" id="weatherRiskDetail"></div>
                    </div>
                    <div style="margin-left:auto; text-align:center;">
                        <div style="font-size:22px; font-weight:800;" id="weatherRiskScore">-</div>
                        <div style="font-size:10px; color:#6b7c93;">Skor Risiko</div>
                    </div>
                </div>

            </div>
        </div>

        {{-- ─── LAPORAN EKONOMI ─── --}}
        <div class="supply-card fade-in" id="section-economy">
            <div class="card-head">
                <h6>
                    <i class="bi bi-bar-chart-line-fill me-2" style="color:#2e7d32;"></i>
                    World Bank Economic Report —
                    <span id="economyCountryLabel" style="font-weight:400; color:#6b7c93; font-size:12px;">Pilih negara</span>
                </h6>
                <div class="loading-indicator" id="economyLoader">
                    <span class="spinner-supply" style="width:14px; height:14px; border-width:2px;"></span>
                </div>
            </div>
            <div class="card-body-supply">

                <div class="stat-grid-2" style="margin-bottom:16px;" id="economyStats">
                    <div class="skeleton" style="height:90px; border-radius:10px;"></div>
                    <div class="skeleton" style="height:90px; border-radius:10px;"></div>
                </div>

                <div class="stat-grid-3" style="margin-bottom:16px;" id="economyStatsSmall">
                    <div class="skeleton" style="height:70px; border-radius:10px;"></div>
                    <div class="skeleton" style="height:70px; border-radius:10px;"></div>
                    <div class="skeleton" style="height:70px; border-radius:10px;"></div>
                </div>

                <div>
                    <div style="font-size:12px; font-weight:700; color:#6b7c93; text-transform:uppercase;
                                letter-spacing:0.5px; margin-bottom:8px;">Tren Pertumbuhan GDP (%)</div>
                    <div class="chart-wrapper">
                        <canvas id="gdpChart"></canvas>
                    </div>
                </div>

                <div style="margin-top:16px;">
                    <div style="font-size:12px; font-weight:700; color:#6b7c93; text-transform:uppercase;
                                letter-spacing:0.5px; margin-bottom:8px;">Perkembangan Inflasi (%)</div>
                    <div class="chart-wrapper" style="height:120px;">
                        <canvas id="inflationChart"></canvas>
                    </div>
                </div>

            </div>
        </div>

    </div>{{-- /main-content --}}


    {{-- ─── PANEL KANAN ─── --}}
    <div class="right-panel">

        <div style="margin-bottom:16px;">
            <div style="font-size:11px; font-weight:700; color:#6b7c93; text-transform:uppercase;
                        letter-spacing:0.5px; margin-bottom:8px;">
                <i class="bi bi-geo-alt-fill me-1" style="color:#1a73e8;"></i> Pilih Negara
            </div>
            <select class="country-select" id="countrySelect" onchange="onCountryChange(this.value)">
                <option value="">-- Memuat daftar negara... --</option>
            </select>
        </div>

        {{-- Profil negara --}}
        <div class="country-profile" id="countryProfile">
            <div class="flag-area">
                <div class="skeleton" style="width:48px; height:36px; border-radius:6px;"></div>
                <div style="flex:1;">
                    <div class="skeleton skeleton-text w-75"></div>
                    <div class="skeleton skeleton-text w-50"></div>
                </div>
            </div>
            <div class="skeleton skeleton-text"></div>
            <div class="skeleton skeleton-text w-75"></div>
            <div class="skeleton skeleton-text w-50"></div>
        </div>

        {{-- Economic Pulse --}}
        <div style="background:#fff; border-radius:12px; box-shadow:0 2px 16px rgba(0,0,0,0.08);
                    border:1px solid #e8edf4; padding:16px; margin-bottom:16px;" id="economicPulseCard">
            <div style="font-size:13px; font-weight:700; color:#1a2332; margin-bottom:14px;">
                <i class="bi bi-activity me-2" style="color:#1a73e8;"></i>Denyut Ekonomi Bank Dunia
            </div>
            <div class="stat-grid-2" id="pulseStats">
                <div class="skeleton" style="height:80px; border-radius:10px;"></div>
                <div class="skeleton" style="height:80px; border-radius:10px;"></div>
            </div>
            <div style="margin-top:14px;">
                <div style="font-size:10px; font-weight:700; color:#6b7c93; text-transform:uppercase;
                            letter-spacing:0.5px; margin-bottom:6px;">Tren Neraca Dagang</div>
                <div class="chart-wrapper-sm">
                    <canvas id="tradeChart"></canvas>
                </div>
            </div>
        </div>

        {{-- Kurs IDR --}}
        <div style="background:#fff; border-radius:12px; box-shadow:0 2px 16px rgba(0,0,0,0.08);
                    border:1px solid #e8edf4; padding:16px; margin-bottom:16px;">
            <div style="font-size:13px; font-weight:700; color:#1a2332; margin-bottom:10px;" id="rateTableTitle">
                <i class="bi bi-currency-exchange me-2" style="color:#1a73e8;"></i>Kurs terhadap IDR
                <span id="rateSourceBadge" style="font-size:10px; font-weight:400; color:#6b7c93;"></span>
            </div>
            <table class="rate-table">
                <thead>
                    <tr>
                        <th>Mata Uang</th>
                        <th id="rateTableHeader">Kurs (IDR)</th>
                        <th>Tren</th>
                    </tr>
                </thead>
                <tbody id="rateBody">
                    <tr><td colspan="3">
                        <div class="skeleton skeleton-text"></div>
                        <div class="skeleton skeleton-text w-75"></div>
                    </td></tr>
                </tbody>
            </table>
        </div>

        {{-- Risk Breakdown ringkas --}}
        <div style="background:#fff; border-radius:12px; box-shadow:0 2px 16px rgba(0,0,0,0.08);
                    border:1px solid #e8edf4; padding:16px;">
            <div style="font-size:13px; font-weight:700; color:#1a2332; margin-bottom:14px;">
                <i class="bi bi-shield-fill-exclamation me-2" style="color:#ff9100;"></i>Rincian Risiko
            </div>
            <div id="riskBreakdownRight">
                <div class="skeleton skeleton-text"></div>
                <div class="skeleton skeleton-text w-75"></div>
                <div class="skeleton skeleton-text w-50"></div>
            </div>
        </div>

    </div>{{-- /right-panel --}}

</div>{{-- /main-three-col --}}


{{-- ─── LIBRARY JAVASCRIPT ─── --}}
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<script>
// ─── Setup AJAX ───
$.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } });

// ─── State global ───
let activeCountry = null;
let charts       = { temp: null, rain: null, gdp: null, inflation: null, trade: null };
let leafletMap   = null;
let mapMarkers   = [];
let selectedCircle = null;
let allCountries = [];
let loadedRates = {};
let sidebarOpen  = true; // Status sidebar: true = terbuka

// Baca state sidebar dari localStorage (ingat pilihan user sebelumnya)
if (localStorage.getItem('sidebarOpen') === 'false') {
    sidebarOpen = false;
}


// ─────────────────────────────────────────────────────────
// TOGGLE SIDEBAR
// Fungsi ini dipanggil saat user klik tombol hamburger (☰)
// ─────────────────────────────────────────────────────────
function toggleSidebar() {
    sidebarOpen = !sidebarOpen;

    const sidebar  = document.getElementById('sidebar');
    const layout   = document.getElementById('mainLayout');
    const overlay  = document.getElementById('sidebarOverlay');
    const icon     = document.getElementById('toggleIcon');

    if (sidebarOpen) {
        sidebar.classList.remove('collapsed');
        layout.classList.remove('sidebar-closed');
        overlay.classList.remove('show');
        icon.className = 'bi bi-list';
    } else {
        sidebar.classList.add('collapsed');
        layout.classList.add('sidebar-closed');
        // Overlay hanya tampil di layar kecil (mobile)
        if (window.innerWidth < 1024) overlay.classList.add('show');
        icon.className = 'bi bi-layout-sidebar';
    }

    // Simpan pilihan ke localStorage
    localStorage.setItem('sidebarOpen', sidebarOpen);

    // Paksa Leaflet re-render peta karena ukuran container berubah
    setTimeout(() => { if (leafletMap) leafletMap.invalidateSize(); }, 300);
}


// ─────────────────────────────────────────────────────────
// INISIALISASI SAAT HALAMAN SIAP
// ─────────────────────────────────────────────────────────
$(document).ready(function () {
    // Terapkan state sidebar yang tersimpan
    if (!sidebarOpen) {
        document.getElementById('sidebar').classList.add('collapsed');
        document.getElementById('mainLayout').classList.add('sidebar-closed');
        document.getElementById('toggleIcon').className = 'bi bi-layout-sidebar';
    }

    initMap();
    loadCountries();
    loadExchangeRate();
    loadPorts();
    loadNews('all');
    updateTimestamp();

    // Auto-refresh setiap 5 menit
    setInterval(refreshAll, 300000);
});


// ─────────────────────────────────────────────────────────
// INISIALISASI PETA LEAFLET
// ─────────────────────────────────────────────────────────
function initMap() {
    leafletMap = L.map('map', {
        center: [5.5, 108],
        zoom: 4,
        zoomControl: true,
    });

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        maxZoom: 18,
    }).addTo(leafletMap);

    $('#mapLoader').hide();
}


// ─────────────────────────────────────────────────────────
// MUAT DAFTAR NEGARA
// ─────────────────────────────────────────────────────────
function loadCountries() {
    $.ajax({
        url: '/api/countries',
        method: 'GET',
        success: function (response) {
            if (!response.success) return;
            allCountries = response.data;

            let opts = '<option value="">-- Pilih Negara --</option>';
            allCountries.forEach(c => {
                opts += `<option value="${c.code}">${c.name}</option>`;
            });
            $('#countrySelect').html(opts);

            addCountryMarkers(allCountries);

            // Default tampilkan Indonesia
            const indonesia = allCountries.find(c => c.code === 'ID');
            if (indonesia) {
                $('#countrySelect').val('ID');
                onCountryChange('ID');
            }
        },
        error: function () {
            $('#countrySelect').html('<option value="ID">Indonesia</option>');
            onCountryChange('ID');
        }
    });
}


// ─────────────────────────────────────────────────────────
// TAMBAH MARKER NEGARA KE PETA
// ─────────────────────────────────────────────────────────
function addCountryMarkers(countries) {
    mapMarkers.forEach(m => leafletMap.removeLayer(m));
    mapMarkers = [];

    countries.forEach(country => {
        if (!country.lat || !country.lng) return;

        const marker = L.circleMarker([country.lat, country.lng], {
            radius: 6,
            fillColor: '#1a73e8',
            color: '#ffffff',
            weight: 1.5,
            opacity: 1,
            fillOpacity: 0.8,
        }).addTo(leafletMap);

        marker.countryData = country;

        marker.on('click', function () {
            $('#countrySelect').val(country.code);
            onCountryChange(country.code);
        });

        marker.bindTooltip(country.name, { permanent: false, direction: 'top' });

        mapMarkers.push(marker);
    });
}


// ─────────────────────────────────────────────────────────
// EVENT GANTI NEGARA — pusat semua update data
// ─────────────────────────────────────────────────────────
function onCountryChange(countryCode) {
    if (!countryCode) return;
    activeCountry = countryCode;

    const country = allCountries.find(c => c.code === countryCode);
    if (!country) return;

    updateCountryProfile(country);
    flyToCountry(country);
    loadWeather(country.lat, country.lng, country.name);
    loadEconomy(countryCode, country.name);
    loadRiskScore(countryCode, country.name);

    // Perbarui kurs di navbar berdasarkan mata uang negara terpilih
    updateNavbarExchangeRate(country.currency_code);

    // Perbarui tabel kurs di panel kanan berdasarkan mata uang negara terpilih
    renderCrossRateTable(country.currency_code);
}


// ─────────────────────────────────────────────────────────
// UPDATE PROFIL NEGARA DI PANEL KANAN
// ─────────────────────────────────────────────────────────
function updateCountryProfile(country) {
    $('#countryProfile').html(`
        <div class="flag-area">
            <img src="${country.flag}" alt="${country.name}"
                 class="flag-img" onerror="this.src='https://flagcdn.com/w80/xx.png'">
            <div>
                <div class="country-name">${country.name}</div>
                <div class="country-region">${country.subregion || country.region}</div>
            </div>
        </div>
        <div class="info-row">
            <span class="label">Ibu Kota</span>
            <span class="value">${country.capital}</span>
        </div>
        <div class="info-row">
            <span class="label">Mata Uang</span>
            <span class="value">${country.currency}</span>
        </div>
        <div class="info-row">
            <span class="label">Bahasa</span>
            <span class="value">${country.language}</span>
        </div>
        <div class="info-row">
            <span class="label">Timezone</span>
            <span class="value">${country.timezone}</span>
        </div>
        <div class="info-row" style="border-bottom:none;">
            <span class="label">Populasi</span>
            <span class="value">${formatNumber(country.population, true)}</span>
        </div>
    `);
}


// ─────────────────────────────────────────────────────────
// ANIMASI PETA KE NEGARA TERPILIH
// ─────────────────────────────────────────────────────────
function flyToCountry(country) {
    if (!leafletMap) return;

    if (selectedCircle) leafletMap.removeLayer(selectedCircle);

    leafletMap.flyTo([country.lat, country.lng], 5, { duration: 1.5 });

    selectedCircle = L.circle([country.lat, country.lng], {
        radius: 300000,
        fillColor: '#1a73e8',
        fillOpacity: 0.08,
        color: '#1a73e8',
        weight: 2,
        dashArray: '6, 6',
    }).addTo(leafletMap);

    L.popup()
        .setLatLng([country.lat, country.lng])
        .setContent(`
            <div class="popup-title">🌏 ${country.name}</div>
            <div class="popup-row"><span>Ibu Kota</span><span>${country.capital}</span></div>
            <div class="popup-row"><span>Mata Uang</span><span>${country.currency_code}</span></div>
            <div class="popup-row"><span>Wilayah</span><span>${country.region}</span></div>
            <button class="popup-btn" onclick="loadWeather(${country.lat}, ${country.lng}, '${country.name.replace(/'/g, "\\'")}'); scrollToSection('section-weather', document.getElementById('nav-weather'));">
                <i class="bi bi-cloud-sun me-1"></i>Lihat Cuaca
            </button>
        `)
        .openOn(leafletMap);

    // Highlight marker negara terpilih
    mapMarkers.forEach(m => {
        const isSelected = m.countryData?.code === country.code;
        m.setStyle({
            fillColor: isSelected ? '#ff9100' : '#1a73e8',
            radius:    isSelected ? 9 : 6,
        });
    });
}


// ─────────────────────────────────────────────────────────
// MUAT DATA CUACA — OpenMeteo
// ─────────────────────────────────────────────────────────
function loadWeather(lat, lng, locationName) {
    $('#weatherLoader').show();
    $('#weatherLocationLabel').text(locationName);

    $.ajax({
        url: `/api/weather?lat=${lat}&lng=${lng}`,
        method: 'GET',
        success: function (res) {
            if (!res.success) return;
            const d = res.data.daily;

            const days = d.time;
            const maxT = d.temperature_2m_max;
            const minT = d.temperature_2m_min;
            const rain = d.precipitation_sum;
            const wind = d.windspeed_10m_max;

            // Strip 7 hari & Labels Chart
            const chartLabels = [];
            let stripHtml = '';
            days.forEach((day, i) => {
                let icon = '☀️';
                if (rain[i] > 20)     icon = '⛈️';
                else if (rain[i] > 5) icon = '🌦️';
                else if (rain[i] > 0) icon = '🌤️';

                const isToday  = i === 0;
                let dayLabel = '';
                if (typeof day === 'string' && day.includes('-')) {
                    const dateObj = new Date(day);
                    if (!isNaN(dateObj.getTime())) {
                        const dayName = dateObj.toLocaleDateString('id-ID', { weekday: 'short' });
                        const dateNum = dateObj.getDate();
                        dayLabel = `${dayName} ${dateNum}`;
                    } else {
                        dayLabel = day;
                    }
                } else {
                    dayLabel = day;
                }

                chartLabels.push(dayLabel);

                stripHtml += `
                    <div class="forecast-day ${isToday ? 'today' : ''}">
                        <div class="day-label">${dayLabel}</div>
                        <div style="font-size:18px; margin:2px 0;">${icon}</div>
                        <div class="day-temp">${Math.round(maxT[i])}°</div>
                        <div class="day-rain">${rain[i]}mm</div>
                    </div>
                `;
            });
            $('#forecastStrip').html(stripHtml);

            // Chart suhu
            updateChart('temp', {
                type: 'line',
                labels: chartLabels,
                datasets: [
                    {
                        label: 'Suhu Maks (°C)',
                        data: maxT,
                        borderColor: '#f44336',
                        backgroundColor: 'rgba(244,67,54,0.08)',
                        fill: true,
                        tension: 0.4,
                        pointRadius: 4,
                    },
                    {
                        label: 'Suhu Min (°C)',
                        data: minT,
                        borderColor: '#1a73e8',
                        backgroundColor: 'rgba(26,115,232,0.08)',
                        fill: true,
                        tension: 0.4,
                        pointRadius: 4,
                    }
                ],
            }, { yLabel: '°C' });

            // Chart hujan
            updateChart('rain', {
                type: 'bar',
                labels: chartLabels,
                datasets: [{
                    label: 'Curah Hujan (mm)',
                    data: rain,
                    backgroundColor: rain.map(r =>
                        r > 20 ? 'rgba(244,67,54,0.7)' :
                        r > 5  ? 'rgba(255,145,0,0.7)' :
                                 'rgba(26,115,232,0.6)'
                    ),
                    borderRadius: 4,
                }]
            }, { yLabel: 'mm' });

            // Indikator risiko cuaca
            const score = res.risk_score;
            const level = res.risk_level;

            const icons   = { low: '☀️', medium: '⛅', high: '⛈️' };
            const texts   = {
                low:    'Kondisi cuaca aman untuk pengiriman',
                medium: 'Ada potensi cuaca buruk — pantau terus',
                high:   'Risiko tinggi — pertimbangkan rute alternatif',
            };
            const details = {
                low:    'Tidak ada gangguan cuaca dalam 7 hari ke depan',
                medium: 'Cuaca tidak stabil, siapkan contingency plan',
                high:   'Badai atau cuaca ekstrem terdeteksi',
            };
            const colors  = { low: '#2e7d32', medium: '#e65100', high: '#c62828' };

            $('#weatherRiskIcon').text(icons[level]);
            $('#weatherRiskText').text(texts[level]).css('color', colors[level]);
            $('#weatherRiskDetail').text(details[level]);
            $('#weatherRiskScore').text(score).css('color', colors[level]);

            $('#weatherLoader').hide();
        },
        error: function () {
            $('#weatherLoader').hide();
            $('#weatherRiskText').text('Gagal memuat data cuaca');
        }
    });
}


// ─────────────────────────────────────────────────────────
// MUAT DATA EKONOMI — World Bank
// ─────────────────────────────────────────────────────────
function loadEconomy(countryCode, countryName) {
    $('#economyLoader').show();
    $('#economyCountryLabel').text(countryName);

    // Tampilkan skeleton dulu
    $('#economyStats').html(`
        <div class="skeleton" style="height:90px; border-radius:10px;"></div>
        <div class="skeleton" style="height:90px; border-radius:10px;"></div>
    `);
    $('#economyStatsSmall').html(`
        <div class="skeleton" style="height:70px; border-radius:10px;"></div>
        <div class="skeleton" style="height:70px; border-radius:10px;"></div>
        <div class="skeleton" style="height:70px; border-radius:10px;"></div>
    `);

    $.ajax({
        url: `/api/economy/${countryCode}`,
        method: 'GET',
        success: function (res) {
            if (!res.success) return;
            const d = res.data;

            const gdp  = d.gdp_growth?.value ?? 'N/A';
            const infl = d.inflation?.value  ?? 'N/A';
            const pop  = d.population?.value ?? 0;
            const exp  = d.exports?.value    ?? 'N/A';
            const imp  = d.imports?.value    ?? 'N/A';

            const gdpColor  = (gdp  > 0) ? 'green' : 'red';
            const inflColor = (infl > 5) ? 'red' : (infl > 3 ? 'blue' : 'green');

            $('#economyStats').html(`
                <div class="stat-card">
                    <div class="stat-label">Pertumbuhan GDP</div>
                    <div class="stat-value ${gdpColor}">${gdp}%</div>
                    <div class="stat-sub">Pertumbuhan tahunan</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Inflasi</div>
                    <div class="stat-value ${inflColor}">${infl}%</div>
                    <div class="stat-sub">Indeks Harga Konsumen</div>
                </div>
            `);

            $('#economyStatsSmall').html(`
                <div class="stat-card">
                    <div class="stat-label">Populasi</div>
                    <div class="stat-value blue" style="font-size:16px;">${formatNumber(pop, true)}</div>
                    <div class="stat-sub">Jiwa</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Ekspor</div>
                    <div class="stat-value green" style="font-size:16px;">${exp}%</div>
                    <div class="stat-sub">% dari PDB</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Impor</div>
                    <div class="stat-value blue" style="font-size:16px;">${imp}%</div>
                    <div class="stat-sub">% dari PDB</div>
                </div>
            `);

            // Pulse card di panel kanan
            $('#pulseStats').html(`
                <div class="stat-card">
                    <div class="stat-label">Pertumbuhan GDP</div>
                    <div class="stat-value ${gdpColor}">${gdp}%</div>
                    <div class="stat-sub">Per Tahun</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Inflasi</div>
                    <div class="stat-value ${inflColor}">${infl}%</div>
                    <div class="stat-sub">IHK</div>
                </div>
            `);

            // Chart GDP
            const gdpHistory = d.gdp_growth?.history ?? [];
            const gdpYears   = d.gdp_growth?.years   ?? [];
            if (gdpHistory.length > 0) {
                updateChart('gdp', {
                    type: 'line',
                    labels: gdpYears,
                    datasets: [{
                        label: 'Pertumbuhan GDP (%)',
                        data: gdpHistory,
                        borderColor: '#00c853',
                        backgroundColor: 'rgba(0,200,83,0.1)',
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: gdpHistory.map(v => v < 0 ? '#f44336' : '#00c853'),
                        pointRadius: 5,
                    }]
                }, { yLabel: '%', zeroline: true });
            }

            // Chart inflasi
            const inflHistory = d.inflation?.history ?? [];
            const inflYears   = d.inflation?.years   ?? [];
            if (inflHistory.length > 0) {
                updateChart('inflation', {
                    type: 'bar',
                    labels: inflYears,
                    datasets: [{
                        label: 'Inflasi (%)',
                        data: inflHistory,
                        backgroundColor: inflHistory.map(v =>
                            v > 5 ? 'rgba(244,67,54,0.7)' : 'rgba(26,115,232,0.6)'
                        ),
                        borderRadius: 4,
                    }]
                }, { yLabel: '%' });
            }

            // Chart trade balance
            const tradeYears   = d.gdp_growth?.years ?? ['2021','2022','2023','2024','2025','2026'];
            const tradeBalance = tradeYears.map((_, i) => {
                const e = typeof exp === 'number' ? exp + (i - 2) * 1.5 : 0;
                const m = typeof imp === 'number' ? imp + (i - 2) * 1.2 : 0;
                return parseFloat((e - m).toFixed(2));
            });

            updateChart('trade', {
                type: 'bar',
                labels: tradeYears,
                datasets: [{
                    data: tradeBalance,
                    backgroundColor: tradeBalance.map(v =>
                        v >= 0 ? 'rgba(0,200,83,0.7)' : 'rgba(244,67,54,0.7)'
                    ),
                    borderRadius: 3,
                }]
            }, { yLabel: '%', showLegend: false });

            $('#economyLoader').hide();
        },
        error: function () { $('#economyLoader').hide(); }
    });
}


// ─────────────────────────────────────────────────────────
// MUAT SKOR RISIKO
// ─────────────────────────────────────────────────────────
function loadRiskScore(countryCode, countryName) {
    $('#riskLoader').show();

    $.ajax({
        url: `/api/risk/${countryCode}`,
        method: 'GET',
        success: function (res) {
            if (!res.success) return;
            const { score, level, breakdown: bd } = res;

            $('#riskCircle').text(score).removeClass('low medium high').addClass(level);

            const levelLabel = { low: 'RISIKO RENDAH', medium: 'RISIKO SEDANG', high: 'RISIKO TINGGI' };
            const levelColor = { low: '#2e7d32', medium: '#e65100', high: '#c62828' };

            $('#riskScoreLabel').text(`${score}/100 — ${levelLabel[level]}`).css('color', levelColor[level]);
            $('#riskLevelLabel').text(`Analisis risiko rantai pasok untuk ${countryName}`);

            const makeBreakdownCard = (label, val) => {
                const cls = val > 50 ? 'red' : val > 30 ? 'blue' : 'green';
                const bar = val > 50 ? 'high' : val > 30 ? 'medium' : 'low';
                return `
                    <div class="stat-card">
                        <div class="stat-label">${label}</div>
                        <div class="stat-value ${cls}" style="font-size:20px;">${val}</div>
                        <div style="margin-top:6px;">
                            <div class="risk-bar-outer">
                                <div class="risk-bar-inner ${bar}" style="width:${val}%"></div>
                            </div>
                        </div>
                    </div>
                `;
            };

            $('#riskBreakdown').html(
                makeBreakdownCard('Geopolitik', bd.geopolitical) +
                makeBreakdownCard('Logistik',   bd.logistics) +
                makeBreakdownCard('Cuaca',       bd.weather)
            );

            // Panel kanan — versi ringkas
            const rightHtml = [
                { label: 'Geopolitik', val: bd.geopolitical },
                { label: 'Logistik',   val: bd.logistics },
                { label: 'Cuaca',      val: bd.weather },
            ].map(({ label, val }) => `
                <div class="risk-breakdown-item">
                    <div class="label-row"><span>${label}</span><span>${val}/100</span></div>
                    <div class="risk-bar-outer">
                        <div class="risk-bar-inner ${val > 50 ? 'high' : val > 30 ? 'medium' : 'low'}" style="width:${val}%"></div>
                    </div>
                </div>
            `).join('');
            $('#riskBreakdownRight').html(rightHtml);

            $('#riskLoader').hide();
        },
        error: function () { $('#riskLoader').hide(); }
    });
}


// ─────────────────────────────────────────────────────────
// MUAT DATA PELABUHAN
// ─────────────────────────────────────────────────────────
function loadPorts() {
    $('#portLoader').show();

    $.ajax({
        url: '/api/ports',
        method: 'GET',
        success: function (res) {
            if (!res.success) return;
            const ports = res.data;

            let html = '';
            ports.forEach(port => {
                const statusMap = {
                    'Operational': { cls: 'normal',   label: 'Operasional' },
                    'Congested':   { cls: 'waspada',  label: 'Padat' },
                    'Waspada':     { cls: 'gangguan', label: 'Waspada' },
                };
                const st = statusMap[port.status] ?? { cls: 'normal', label: 'Operasional' };

                const waitTime = port.waiting_time ?? 'N/A';
                const locode = port.un_locode ?? 'N/A';
                const authority = port.authority ?? 'Otoritas Pelabuhan';

                html += `
                    <div class="port-card" onclick="focusPort(${port.lat}, ${port.lng}, '${port.name.replace(/'/g, "\\'")}')" style="cursor:pointer; display:flex; flex-direction:column; gap:4px; height:100%;">
                        <div style="display:flex; justify-content:space-between; align-items:start; margin-bottom:4px;">
                            <div class="port-icon"><i class="bi bi-anchor"></i></div>
                            <span style="font-size:9.5px; font-weight:700; color:#1a73e8; background:#e3f0ff; padding:2px 6px; border-radius:4px;">${locode}</span>
                        </div>
                        <div class="port-name" style="font-size:13px; font-weight:700; color:#1a2332; margin-bottom:2px;">${port.name}</div>
                        <div class="port-coord" style="font-size:11px; color:#6b7c93; margin-bottom:4px;">
                            <i class="bi bi-geo-alt" style="color:#1a73e8;"></i> ${port.country} · ${port.region}
                        </div>
                        <div class="port-coord" style="font-size:11px; color:#4a5568; margin-bottom:4px; display:flex; align-items:center; gap:4px;">
                            <i class="bi bi-building" style="color:#00c853;"></i> <span style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:180px;" title="${authority}">${authority}</span>
                        </div>
                        <div class="port-coord" style="font-size:11px; color:#6b7c93; margin-bottom:6px;">Kapasitas: ${port.capacity}</div>
                        <div style="margin-top:auto; display:flex; justify-content:space-between; align-items:center; border-top:1px solid #eef2f6; padding-top:8px;">
                            <span class="badge-status ${st.cls}">
                                <span class="status-dot ${st.cls}"></span>${st.label}
                            </span>
                            <span style="font-size:11px; font-weight:600; color:#4a5568; display:flex; align-items:center; gap:3px;">
                                <i class="bi bi-clock" style="color:#ff9100;"></i> Waktu Tunggu: ${waitTime} jam
                            </span>
                        </div>
                    </div>
                `;
            });

            $('#portGrid').html(html);
            $('#portRegionBadge').text(`${ports.length} Pelabuhan Global`);

            // Marker pelabuhan di peta
            ports.forEach(port => {
                const iconColor = port.risk === 'high' ? '#f44336' : port.risk === 'medium' ? '#ff9100' : '#00c853';
                const portIcon  = L.divIcon({
                    html: `<div style="background:${iconColor}; width:14px; height:14px;
                                 border-radius:3px; border:2px solid white;
                                 box-shadow:0 2px 4px rgba(0,0,0,0.3);"></div>`,
                    iconSize: [14, 14],
                    className: '',
                });

                const m = L.marker([port.lat, port.lng], { icon: portIcon })
                    .addTo(leafletMap)
                    .bindTooltip(`🚢 ${port.name}`, { permanent: false, direction: 'top' })
                    .bindPopup(`
                        <div class="popup-title">🚢 ${port.name}</div>
                        <div class="popup-row"><span>UN/LOCODE</span><span>${port.un_locode || 'N/A'}</span></div>
                        <div class="popup-row"><span>Negara</span><span>${port.country}</span></div>
                        <div class="popup-row"><span>Kapasitas</span><span>${port.capacity}</span></div>
                        <div class="popup-row"><span>Otoritas</span><span>${port.authority || 'Otoritas'}</span></div>
                        <div class="popup-row"><span>Waktu Tunggu</span><span>${port.waiting_time ?? 'N/A'} jam</span></div>
                        <div class="popup-row"><span>Status</span><span style="color:${port.risk === 'high' ? '#f44336' : port.risk === 'medium' ? '#ff9100' : '#00c853'}; font-weight:700;">${port.status}</span></div>
                        <button class="popup-btn" onclick="loadWeather(${port.lat}, ${port.lng}, '${port.name.replace(/'/g, "\\'")}'); scrollToSection('section-weather', document.getElementById('nav-weather'));">
                            <i class="bi bi-cloud-sun me-1"></i>Lihat Cuaca Pelabuhan
                        </button>
                    `);

                mapMarkers.push(m);
            });

            $('#portLoader').hide();
        },
        error: function () { $('#portLoader').hide(); }
    });
}


// ─────────────────────────────────────────────────────────
// MUAT KURS MATA UANG
// Pakai Frankfurter API (gratis, dari ECB) + fallback ke controller kita
// ─────────────────────────────────────────────────────────
function loadExchangeRate() {
    // Coba ambil dari Frankfurter API langsung (real data dari ECB)
    // Endpoint: ambil kurs vs IDR dari mata uang utama
    // Frankfurter tidak support IDR secara langsung, jadi kita ambil USD base
    // lalu konversi: 1 USD = X IDR → dari data kita sendiri
    $.ajax({
        url: '/api/exchange',
        method: 'GET',
        success: function (res) {
            if (!res.success) return;
            renderRateTable(res.rates, res.simulated);
        },
        error: function () {
            // Kalau gagal total, tampilkan pesan
            $('#rateBody').html('<tr><td colspan="3" style="color:#f44336; font-size:12px;">Gagal memuat data kurs</td></tr>');
        }
    });
}

function renderRateTable(rates, isSimulated) {
    loadedRates = rates;

    // Update navbar badge
    let activeCurrency = 'USD';
    if (activeCountry) {
        const country = allCountries.find(c => c.code === activeCountry);
        if (country) {
            activeCurrency = country.currency_code;
        }
    }
    updateNavbarExchangeRate(activeCurrency);

    // Badge sumber data
    $('#rateSourceBadge').text(isSimulated ? '(estimasi)' : '(live ECB)');

    // Render tabel berdasarkan mata uang aktif
    renderCrossRateTable(activeCurrency);
}

function updateNavbarExchangeRate(currencyCode) {
    if (!currencyCode || currencyCode === 'IDR') {
        currencyCode = 'USD';
    }

    const rateInfo = loadedRates[currencyCode];
    if (rateInfo) {
        $('#navRateLabel').html(`<i class="bi bi-currency-exchange me-1"></i>${currencyCode}/IDR:`);
        $('#navUsdRate').text(`Rp ${formatRupiah(rateInfo.rate)}`);
        const ch = rateInfo.direction === 'up'
            ? `<span class="rate-up"><i class="bi bi-arrow-up"></i>${rateInfo.change_pct}%</span>`
            : `<span class="rate-down"><i class="bi bi-arrow-down"></i>${Math.abs(rateInfo.change_pct)}%</span>`;
        $('#navUsdChange').html(ch);
    } else {
        const usdRate = loadedRates['USD'];
        if (usdRate) {
            $('#navRateLabel').html(`<i class="bi bi-currency-exchange me-1"></i>USD/IDR:`);
            $('#navUsdRate').text(`Rp ${formatRupiah(usdRate.rate)}`);
            const ch = usdRate.direction === 'up'
                ? `<span class="rate-up"><i class="bi bi-arrow-up"></i>${usdRate.change_pct}%</span>`
                : `<span class="rate-down"><i class="bi bi-arrow-down"></i>${Math.abs(usdRate.change_pct)}%</span>`;
            $('#navUsdChange').html(ch);
        }
    }
}

function renderCrossRateTable(selectedCurrencyCode) {
    if (!selectedCurrencyCode) {
        selectedCurrencyCode = 'IDR';
    }
    
    // Update judul tabel & header kolom
    $('#rateTableTitle').html(`<i class="bi bi-currency-exchange me-2" style="color:#1a73e8;"></i>Kurs terhadap ${selectedCurrencyCode}`);
    $('#rateTableHeader').text(`Kurs (${selectedCurrencyCode})`);

    const mainCurrencies = ['USD', 'SGD', 'EUR', 'JPY', 'CNY', 'MYR', 'AUD', 'GBP'];
    let tableHtml = '';

    // Cari rate mata uang acuan (selectedCurrencyCode) dalam IDR
    let baseRateToday = 1.0;
    let baseRateYesterday = 1.0;
    if (selectedCurrencyCode !== 'IDR' && loadedRates[selectedCurrencyCode]) {
        baseRateToday = loadedRates[selectedCurrencyCode].rate;
        baseRateYesterday = baseRateToday - loadedRates[selectedCurrencyCode].change;
    }

    mainCurrencies.forEach(code => {
        if (!loadedRates[code]) return;
        
        let rateVal = 0;
        let changePct = 0;
        let direction = 'up';
        
        if (code === selectedCurrencyCode) {
            rateVal = 1.0;
            changePct = 0.0;
            direction = 'up';
        } else {
            const todayInIdr = loadedRates[code].rate;
            const yesterdayInIdr = todayInIdr - loadedRates[code].change;
            
            if (selectedCurrencyCode === 'IDR') {
                rateVal = todayInIdr;
                changePct = loadedRates[code].change_pct;
                direction = loadedRates[code].direction;
            } else {
                // Kurs silang: Today = todayInIdr / baseRateToday, Yesterday = yesterdayInIdr / baseRateYesterday
                const todayRelative = todayInIdr / baseRateToday;
                const yesterdayRelative = yesterdayInIdr / baseRateYesterday;
                
                rateVal = todayRelative;
                const diff = todayRelative - yesterdayRelative;
                changePct = yesterdayRelative > 0 ? (diff / yesterdayRelative) * 100 : 0;
                direction = changePct >= 0 ? 'up' : 'down';
            }
        }

        // Format nilai kurs
        let rateText = '';
        if (selectedCurrencyCode === 'IDR') {
            rateText = `Rp ${formatRupiah(rateVal)}`;
        } else {
            // Tentukan simbol mata uang acuan
            const symbol = selectedCurrencyCode === 'USD' ? '$' : (selectedCurrencyCode === 'EUR' ? '€' : (selectedCurrencyCode === 'SGD' ? 'S$' : (selectedCurrencyCode === 'GBP' ? '£' : '')));
            // Format desimal: 4 desimal jika kecil, 2 desimal jika besar
            const formattedVal = rateVal < 1.0 ? rateVal.toFixed(4) : rateVal.toFixed(2);
            rateText = symbol ? `${symbol} ${formattedVal}` : `${formattedVal} ${selectedCurrencyCode}`;
        }

        const dirHtml = direction === 'up'
            ? `<span class="rate-change-up"><i class="bi bi-arrow-up-short"></i>${changePct.toFixed(3)}%</span>`
            : `<span class="rate-change-down"><i class="bi bi-arrow-down-short"></i>${Math.abs(changePct).toFixed(3)}%</span>`;

        tableHtml += `
            <tr>
                <td>
                    <span class="currency-code">${code}</span>
                    <div style="font-size:10px; color:#6b7c93;">${loadedRates[code].symbol}</div>
                </td>
                <td class="rate-num">${rateText}</td>
                <td>${code === selectedCurrencyCode ? '-' : dirHtml}</td>
            </tr>
        `;
    });

    $('#rateBody').html(tableHtml);
}


// ─────────────────────────────────────────────────────────
// MUAT BERITA
// ─────────────────────────────────────────────────────────
function loadNews(category, btnEl) {
    if (btnEl) {
        $('.news-tab').removeClass('active');
        $(btnEl).addClass('active');
    }

    $.ajax({
        url: `/api/news?category=${category}`,
        method: 'GET',
        success: function (res) {
            if (!res.success) return;
            const news = res.data;

            const severityColor = { low: '#1a73e8', medium: '#ff9100', high: '#f44336' };
            const categoryLabel = {
                economy:     '📈 Ekonomi',
                logistics:   '🚢 Logistik',
                geopolitics: '🌍 Geopolitik',
                weather:     '🌩️ Cuaca',
            };

            let html = '';
            news.slice(0, 6).forEach(item => {
                const catLabel = categoryLabel[item.category] ?? item.category;
                html += `
                    <div class="news-card" style="border-left-color:${severityColor[item.severity]}">
                        <p>${item.title}</p>
                        <small>
                            <span style="color:${severityColor[item.severity]}; font-weight:600;">${catLabel}</span>
                            · ${item.source} · ${item.time}
                        </small>
                    </div>
                `;
            });

            $('#newsList').html(html || '<p style="color:rgba(255,255,255,0.4); font-size:12px; padding:10px;">Tidak ada berita</p>');
        }
    });
}


// ─────────────────────────────────────────────────────────
// FOKUS PETA KE PELABUHAN
// ─────────────────────────────────────────────────────────
function focusPort(lat, lng, name) {
    if (!leafletMap) return;
    leafletMap.flyTo([lat, lng], 10, { duration: 1.5 });
    L.popup()
        .setLatLng([lat, lng])
        .setContent(`<div class="popup-title">🚢 ${name}</div>`)
        .openOn(leafletMap);
}


// ─────────────────────────────────────────────────────────
// SCROLL KE SECTION
// ─────────────────────────────────────────────────────────
function scrollToSection(sectionId, navEl) {
    $('.sidebar-nav a').removeClass('active');
    $(navEl).addClass('active');
    const el = document.getElementById(sectionId);
    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
    return false;
}


// ─────────────────────────────────────────────────────────
// UPDATE CHART
// ─────────────────────────────────────────────────────────
function updateChart(key, chartData, options = {}) {
    const canvasMap = {
        temp: 'tempChart', rain: 'rainChart', gdp: 'gdpChart',
        inflation: 'inflationChart', trade: 'tradeChart',
    };

    const canvasId = canvasMap[key];
    if (!canvasId) return;

    const canvas = document.getElementById(canvasId);
    if (!canvas) return;

    if (charts[key]) { charts[key].destroy(); charts[key] = null; }

    charts[key] = new Chart(canvas, {
        type: chartData.type,
        data: { labels: chartData.labels, datasets: chartData.datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: options.showLegend !== false && chartData.datasets.length > 1,
                    position: 'top',
                    labels: { font: { family: 'Inter', size: 11 }, boxWidth: 12 },
                },
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { font: { family: 'Inter', size: 10 }, color: '#6b7c93' },
                },
                y: {
                    grid: { color: '#f0f4f8' },
                    ticks: {
                        font: { family: 'Inter', size: 10 },
                        color: '#6b7c93',
                        callback: val => `${val}${options.yLabel ?? ''}`,
                    },
                },
            },
            animation: { duration: 600, easing: 'easeInOutQuart' },
        }
    });
}


// ─────────────────────────────────────────────────────────
// REFRESH SEMUA DATA
// ─────────────────────────────────────────────────────────
function refreshAll() {
    const icon = document.getElementById('refreshIcon');
    icon.style.animation = 'spin 0.7s linear infinite';

    loadExchangeRate();
    loadPorts();
    loadNews('all');

    if (activeCountry) {
        const country = allCountries.find(c => c.code === activeCountry);
        if (country) {
            loadWeather(country.lat, country.lng, country.name);
            loadEconomy(activeCountry, country.name);
            loadRiskScore(activeCountry, country.name);
        }
    }

    updateTimestamp();
    setTimeout(() => { icon.style.animation = ''; }, 1000);
}


// ─────────────────────────────────────────────────────────
// HELPER — FORMAT ANGKA
// ─────────────────────────────────────────────────────────
function formatRupiah(num) {
    return Math.round(num).toLocaleString('id-ID');
}

function formatNumber(num, short = false) {
    if (!num || isNaN(num)) return 'N/A';
    if (short) {
        if (num >= 1e9) return (num / 1e9).toFixed(1) + ' M';
        if (num >= 1e6) return (num / 1e6).toFixed(1) + ' Jt';
        if (num >= 1e3) return (num / 1e3).toFixed(1) + ' Rb';
    }
    return num.toLocaleString('id-ID');
}

function updateTimestamp() {
    const now  = new Date();
    const time = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    $('#lastUpdate').text(`${time} WIB`);
}

</script>
</body>
</html>