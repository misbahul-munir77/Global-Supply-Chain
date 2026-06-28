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


// TOGGLE SIDEBAR
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


// INISIALISASI SAAT HALAMAN SIAP

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


// INISIALISASI PETA LEAFLET
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



// MUAT DAFTAR NEGARA
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


// TAMBAH MARKER NEGARA KE PETA
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


// EVENT GANTI NEGARA — pusat semua update data
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



// UPDATE PROFIL NEGARA DI PANEL KANAN
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


// ANIMASI PETA KE NEGARA TERPILIH
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


// MUAT DATA CUACA — OpenMeteo
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


// MUAT DATA EKONOMI — World Bank
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


// MUAT SKOR RISIKO
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


// MUAT DATA PELABUHAN
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



// MUAT KURS MATA UANG
// Pakai Frankfurter API (gratis, dari ECB) + fallback ke controller
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



// MUAT BERITA
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



// FOKUS PETA KE PELABUHAN
function focusPort(lat, lng, name) {
    if (!leafletMap) return;
    leafletMap.flyTo([lat, lng], 10, { duration: 1.5 });
    L.popup()
        .setLatLng([lat, lng])
        .setContent(`<div class="popup-title">🚢 ${name}</div>`)
        .openOn(leafletMap);
}



// SCROLL KE SECTION
function scrollToSection(sectionId, navEl) {
    $('.sidebar-nav a').removeClass('active');
    $(navEl).addClass('active');
    const el = document.getElementById(sectionId);
    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
    return false;
}



// UPDATE CHART
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


// REFRESH SEMUA DATA
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


// HELPER — FORMAT ANGKA
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
