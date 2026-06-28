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
        <!--  Kurs USD/IDR — diisi JavaScript -->
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

<!--  LAYOUT UTAMA  -->
<div class="main-three-col" id="mainLayout">

     <!-- SIDEBAR KIRI  -->
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
                    <i class="bi bi-train-freight-front-fill" style="color: #9f9595;"></i> Data Pelabuhan
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


    <!-- KONTEN TENGAH -->
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
                <h6>Peta Jalur Logistik Global</h6>
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

        <!-- DATA PELABUHAN -->
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

        <!-- CUACA & LINGKUNGAN -->
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

<script src="{{ asset('script/script.js') }}"></script>
</body>
</html>