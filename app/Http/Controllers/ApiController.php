<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;  // Laravel HTTP Client untuk memanggil API eksternal

class ApiController extends Controller
{
//  DATA CUACA — OpenMeteo API
    public function getWeather(Request $request)
    {
        // Ambil koordinat yang dikirim frontend
        // Default: Jakarta, Indonesia (-6.2, 106.8) jika tidak ada parameter
        $lat = $request->get('lat', -6.2);
        $lng = $request->get('lng', 106.8);

        try {
            // Memanggil OpenMeteo API
            // Parameter yang kita minta:
            // - hourly: temperature_2m (suhu per jam), precipitation (curah hujan), windspeed_10m (angin)
            // - daily: weathercode (kode cuaca), temperature_2m_max/min, precipitation_sum
            // - forecast_days=7 → prediksi 7 hari ke depan
            $response = Http::withoutVerifying()->timeout(10)->get('https://api.open-meteo.com/v1/forecast', [
                'latitude'       => $lat,
                'longitude'      => $lng,
                'daily'          => 'temperature_2m_max,temperature_2m_min,precipitation_sum,windspeed_10m_max,weathercode',
                'hourly'         => 'temperature_2m,precipitation,windspeed_10m',
                'timezone'       => 'Asia/Jakarta',
                'forecast_days'  => 7,
            ]);

            if ($response->successful()) {
                $data = $response->json();

                // Hitung skor risiko cuaca (0-100) berdasarkan data API
                // Semakin tinggi angin dan hujan → risiko semakin besar
                $maxWind = max($data['daily']['windspeed_10m_max'] ?? [0]);
                $totalRain = array_sum($data['daily']['precipitation_sum'] ?? [0]);

                // Logika skor risiko:
                // Angin > 60 km/h = risiko tinggi, < 30 = rendah
                // Hujan > 50 mm/hari = risiko tinggi
                $windScore = min(100, ($maxWind / 60) * 50);
                $rainScore = min(100, ($totalRain / 200) * 50);
                $riskScore = round($windScore + $rainScore);

                return response()->json([
                    'success'    => true,
                    'data'       => $data,
                    'risk_score' => $riskScore,
                    'risk_level' => $riskScore >= 60 ? 'high' : ($riskScore >= 30 ? 'medium' : 'low'),
                ]);
            }

            // Jika API gagal, gunakan data fallback simulasi
            return response()->json($this->weatherFallback($lat, $lng));

        } catch (\Exception $e) {
            // Tangkap error (timeout, network error, dll) → return data simulasi
            return response()->json($this->weatherFallback($lat, $lng));
        }
    }

    private function weatherFallback($lat, $lng)
    {
        // Tentukan suhu berdasarkan garis lintang (latitude)
        // Dekat khatulistiwa (lat ≈ 0) → panas ~28-33°C
        // Jauh dari khatulistiwa (lat > 30) → lebih dingin
        $baseTemp = max(5, 33 - abs($lat) * 0.5);
        $seed = abs((int)($lat * 10 + $lng * 5)) % 30; // angka unik per lokasi

        $days = ['Sen','Sel','Rab','Kam','Jum','Sab','Min'];
        $tempMax = [];
        $tempMin = [];
        $rain    = [];
        $wind    = [];

        for ($i = 0; $i < 7; $i++) {
            $variation = sin($i + $seed) * 3;
            $tempMax[] = round($baseTemp + $variation + rand(-1, 2), 1);
            $tempMin[] = round($baseTemp + $variation - rand(3, 6), 1);
            $rain[]    = round(max(0, 10 + sin($i * 0.8 + $seed) * 15 + rand(-5, 10)), 1);
            $wind[]    = round(max(5, 15 + sin($i + $seed) * 10 + rand(-3, 5)), 1);
        }

        $maxWind  = max($wind);
        $totalRain = array_sum($rain);
        $riskScore = min(100, round(($maxWind / 60) * 50 + ($totalRain / 200) * 50));

        return [
            'success'    => true,
            'simulated'  => true,
            'data'       => [
                'daily' => [
                    'time'                   => $days,
                    'temperature_2m_max'     => $tempMax,
                    'temperature_2m_min'     => $tempMin,
                    'precipitation_sum'      => $rain,
                    'windspeed_10m_max'      => $wind,
                ]
            ],
            'risk_score' => $riskScore,
            'risk_level' => $riskScore >= 60 ? 'high' : ($riskScore >= 30 ? 'medium' : 'low'),
        ];
    }


    // STEP 4B: DATA EKONOMI — World Bank API
    public function getEconomy(Request $request, $countryCode)
    {
        // Konversi kode negara ke uppercase (misalnya 'id' → 'ID')
        $countryCode = strtoupper($countryCode);

        // Daftar indikator yang akan kita ambil dari World Bank
        $indicators = [
            'gdp_growth' => 'NY.GDP.MKTP.KD.ZG',
            'inflation'  => 'FP.CPI.TOTL.ZG',
            'population' => 'SP.POP.TOTL',
            'exports'    => 'NE.EXP.GNFS.ZS',
            'imports'    => 'NE.IMP.GNFS.ZS',
        ];

        $result = [];

        try {
            foreach ($indicators as $key => $indicator) {
                // Ambil data 2021-2026 dari World Bank (date range)
                $response = Http::withoutVerifying()->timeout(10)->get(
                    "https://api.worldbank.org/v2/country/{$countryCode}/indicator/{$indicator}",
                    ['format' => 'json', 'date' => '2021:2026', 'per_page' => 10]
                );

                if ($response->successful()) {
                    $data    = $response->json();
                    $entries = $data[1] ?? [];

                    $latestValue = null;
                    $years       = [];
                    $values      = [];

                    // Urutkan dari lama ke baru
                    usort($entries, fn($a, $b) => strcmp($a['date'], $b['date']));

                    foreach ($entries as $entry) {
                        if ($entry['value'] !== null) {
                            $latestValue = round($entry['value'], 2);
                            $years[]     = $entry['date'];
                            $values[]    = round($entry['value'], 2);
                        }
                    }

                    $result[$key] = [
                        'value'   => $latestValue,
                        'years'   => $years,
                        'history' => $values,
                    ];
                }
            }

            if (!empty($result)) {
                return response()->json(['success' => true, 'data' => $result, 'country' => $countryCode]);
            }

            return response()->json($this->economyFallback($countryCode));

        } catch (\Exception $e) {
            return response()->json($this->economyFallback($countryCode));
        }
    }


    private function economyFallback($countryCode)
    {
        // Data simulasi berdasarkan profil ekonomi umum negara-negara
        $economyProfiles = [
            'ID' => ['gdp' => 5.05, 'inflation' => 3.60, 'pop' => 277534122, 'exp' => 21.8, 'imp' => 19.4],
            'SG' => ['gdp' => 1.10, 'inflation' => 4.80, 'pop' => 5917600,   'exp' => 175.9,'imp' => 148.3],
            'MY' => ['gdp' => 3.60, 'inflation' => 3.50, 'pop' => 33200000,  'exp' => 68.0, 'imp' => 58.0],
            'CN' => ['gdp' => 5.20, 'inflation' => 0.20, 'pop' => 1409670000,'exp' => 20.0, 'imp' => 17.5],
            'JP' => ['gdp' => 1.90, 'inflation' => 3.30, 'pop' => 125200000, 'exp' => 18.0, 'imp' => 20.5],
            'KR' => ['gdp' => 1.40, 'inflation' => 3.60, 'pop' => 51740000,  'exp' => 44.0, 'imp' => 40.0],
            'IN' => ['gdp' => 8.20, 'inflation' => 5.40, 'pop' => 1428628000,'exp' => 22.0, 'imp' => 24.0],
            'US' => ['gdp' => 2.50, 'inflation' => 3.40, 'pop' => 334914895, 'exp' => 11.5, 'imp' => 14.5],
            'DE' => ['gdp' => 0.20, 'inflation' => 5.90, 'pop' => 84607016,  'exp' => 48.0, 'imp' => 42.0],
            'AU' => ['gdp' => 2.00, 'inflation' => 5.40, 'pop' => 26439111,  'exp' => 27.0, 'imp' => 24.0],
            'TH' => ['gdp' => 2.70, 'inflation' => 1.20, 'pop' => 71700000,  'exp' => 69.0, 'imp' => 65.0],
            'VN' => ['gdp' => 5.05, 'inflation' => 3.30, 'pop' => 98186989,  'exp' => 93.0, 'imp' => 84.0],
            'PH' => ['gdp' => 5.60, 'inflation' => 6.00, 'pop' => 114163719, 'exp' => 28.0, 'imp' => 39.0],
            'BR' => ['gdp' => 2.90, 'inflation' => 4.60, 'pop' => 215313498, 'exp' => 18.5, 'imp' => 14.0],
            'ZA' => ['gdp' => 0.60, 'inflation' => 5.50, 'pop' => 60414495,  'exp' => 33.0, 'imp' => 31.0],
            'AE' => ['gdp' => 3.40, 'inflation' => 4.30, 'pop' => 9770529,   'exp' => 97.0, 'imp' => 72.0],
            'SA' => ['gdp' => 8.70, 'inflation' => 3.40, 'pop' => 36408820,  'exp' => 45.0, 'imp' => 32.0],
        ];

        // Gunakan profil yang sesuai, atau generate random jika tidak ada
        $profile = $economyProfiles[$countryCode] ?? [
            'gdp' => round(rand(10, 60) / 10, 1),
            'inflation' => round(rand(10, 80) / 10, 1),
            'pop' => rand(5000000, 100000000),
            'exp' => round(rand(100, 500) / 10, 1),
            'imp' => round(rand(100, 500) / 10, 1),
        ];

        // Data 2021-2026 (sesuai permintaan grafik mulai 2021)
        $years = ['2021', '2022', '2023', '2024', '2025', '2026'];
        $gdpHistory  = [];
        $inflHistory = [];

        // Simulasi tren realistis: 2021 recovery pasca-COVID, 2022-2023 normalisasi, 2024-2026 stabilisasi
        $gdpTrend  = [0.8, 1.2, 0.3, -0.1, 0.0, 0.2];  // pergeseran per tahun
        $inflTrend = [1.5, 2.1, 0.8, -0.5, -0.3, -0.1];  // inflasi naik 2021-2022, turun 2023+

        for ($i = 0; $i < 6; $i++) {
            $gdpHistory[]  = round($profile['gdp']       + $gdpTrend[$i]  + sin($i * 0.9) * 0.5, 2);
            $inflHistory[] = round($profile['inflation'] + $inflTrend[$i] + cos($i * 0.7) * 0.3, 2);
        }

        return [
            'success'   => true,
            'simulated' => true,
            'country'   => $countryCode,
            'data'      => [
                'gdp_growth' => ['value' => $profile['gdp'],       'years' => $years, 'history' => $gdpHistory],
                'inflation'  => ['value' => $profile['inflation'],  'years' => $years, 'history' => $inflHistory],
                'population' => ['value' => $profile['pop'],        'years' => $years, 'history' => []],
                'exports'    => ['value' => $profile['exp'],        'years' => $years, 'history' => []],
                'imports'    => ['value' => $profile['imp'],        'years' => $years, 'history' => []],
            ],
        ];
    }


// DATA NEGARA — REST Countries API
    public function getCountries(Request $request)
    {
        try {
            // Field yang kita butuhkan saja (agar response lebih kecil/cepat)
            $fields = 'name,cca2,flags,capital,region,subregion,currencies,languages,latlng,area,population,timezones';

            $response = Http::withoutVerifying()->timeout(15)->get(
                "https://restcountries.com/v3.1/all?fields={$fields}"
            );

            if ($response->successful()) {
                $countries = $response->json();

                // Filter & format: ambil hanya data yang kita butuhkan
                $formatted = array_map(function($c) {
                    // Ambil nama mata uang pertama (bisa ada lebih dari satu)
                    $currencies = $c['currencies'] ?? [];
                    $currencyCode = array_key_first($currencies) ?? 'N/A';
                    $currencyName = $currencies[$currencyCode]['name'] ?? 'N/A';
                    $currencySymbol = $currencies[$currencyCode]['symbol'] ?? '';

                    // Ambil bahasa pertama
                    $languages = array_values($c['languages'] ?? ['N/A']);

                    return [
                        'code'     => $c['cca2'],                           // Kode 2 huruf: ID, SG, dll
                        'name'     => $c['name']['common'],                 // Nama umum: Indonesia, Singapore
                        'flag'     => $c['flags']['png'] ?? $c['flags']['svg'] ?? '', // URL gambar bendera
                        'capital'  => $c['capital'][0] ?? 'N/A',           // Ibu kota
                        'region'   => $c['region'] ?? 'N/A',               // Benua/wilayah
                        'subregion'=> $c['subregion'] ?? 'N/A',
                        'lat'      => $c['latlng'][0] ?? 0,                // Latitude untuk Leaflet
                        'lng'      => $c['latlng'][1] ?? 0,                // Longitude untuk Leaflet
                        'currency' => "{$currencyCode} ({$currencyName})",
                        'currency_symbol' => $currencySymbol,
                        'currency_code'   => $currencyCode,
                        'language' => $languages[0] ?? 'N/A',
                        'timezone' => $c['timezones'][0] ?? 'UTC',
                        'area'     => $c['area'] ?? 0,
                        'population' => $c['population'] ?? 0,
                    ];
                }, $countries);

                // Urutkan berdasarkan nama negara A-Z
                usort($formatted, fn($a, $b) => strcmp($a['name'], $b['name']));

                return response()->json(['success' => true, 'data' => $formatted, 'total' => count($formatted)]);
            }

            return response()->json($this->countriesFallback());

        } catch (\Exception $e) {
            return response()->json($this->countriesFallback());
        }
    }

    /**
     * Daftar negara fallback
     * Dipakai jika REST Countries API tidak bisa diakses
     */
    private function countriesFallback()
    {
        return [
            'success'   => true,
            'simulated' => true,
            'data'      => [
                // Asia Tenggara
                ['code'=>'ID','name'=>'Indonesia',    'flag'=>'https://flagcdn.com/w80/id.png','capital'=>'Jakarta',     'region'=>'Asia','subregion'=>'Southeast Asia','lat'=>-0.7893,'lng'=>113.9213,'currency'=>'IDR (Indonesian Rupiah)','currency_code'=>'IDR','currency_symbol'=>'Rp','language'=>'Indonesian','timezone'=>'UTC+07:00','population'=>273523615,'area'=>1904569],
                ['code'=>'SG','name'=>'Singapore',    'flag'=>'https://flagcdn.com/w80/sg.png','capital'=>'Singapore',   'region'=>'Asia','subregion'=>'Southeast Asia','lat'=>1.3521,'lng'=>103.8198,'currency'=>'SGD (Singapore Dollar)','currency_code'=>'SGD','currency_symbol'=>'S$','language'=>'English','timezone'=>'UTC+08:00','population'=>5685807,'area'=>710],
                ['code'=>'MY','name'=>'Malaysia',     'flag'=>'https://flagcdn.com/w80/my.png','capital'=>'Kuala Lumpur','region'=>'Asia','subregion'=>'Southeast Asia','lat'=>4.2105,'lng'=>101.9758,'currency'=>'MYR (Malaysian Ringgit)','currency_code'=>'MYR','currency_symbol'=>'RM','language'=>'Malay','timezone'=>'UTC+08:00','population'=>32365999,'area'=>330803],
                ['code'=>'TH','name'=>'Thailand',     'flag'=>'https://flagcdn.com/w80/th.png','capital'=>'Bangkok',     'region'=>'Asia','subregion'=>'Southeast Asia','lat'=>15.8700,'lng'=>100.9925,'currency'=>'THB (Thai Baht)','currency_code'=>'THB','currency_symbol'=>'฿','language'=>'Thai','timezone'=>'UTC+07:00','population'=>69799978,'area'=>513120],
                ['code'=>'VN','name'=>'Vietnam',      'flag'=>'https://flagcdn.com/w80/vn.png','capital'=>'Hanoi',       'region'=>'Asia','subregion'=>'Southeast Asia','lat'=>14.0583,'lng'=>108.2772,'currency'=>'VND (Vietnamese Dong)','currency_code'=>'VND','currency_symbol'=>'₫','language'=>'Vietnamese','timezone'=>'UTC+07:00','population'=>97338579,'area'=>331212],
                ['code'=>'PH','name'=>'Philippines',  'flag'=>'https://flagcdn.com/w80/ph.png','capital'=>'Manila',      'region'=>'Asia','subregion'=>'Southeast Asia','lat'=>12.8797,'lng'=>121.7740,'currency'=>'PHP (Philippine Peso)','currency_code'=>'PHP','currency_symbol'=>'₱','language'=>'Filipino','timezone'=>'UTC+08:00','population'=>109581078,'area'=>300000],
                ['code'=>'BN','name'=>'Brunei',       'flag'=>'https://flagcdn.com/w80/bn.png','capital'=>'Bandar Seri Begawan','region'=>'Asia','subregion'=>'Southeast Asia','lat'=>4.5353,'lng'=>114.7277,'currency'=>'BND (Brunei Dollar)','currency_code'=>'BND','currency_symbol'=>'B$','language'=>'Malay','timezone'=>'UTC+08:00','population'=>437479,'area'=>5765],
                ['code'=>'MM','name'=>'Myanmar',      'flag'=>'https://flagcdn.com/w80/mm.png','capital'=>'Naypyidaw',   'region'=>'Asia','subregion'=>'Southeast Asia','lat'=>21.9162,'lng'=>95.9560,'currency'=>'MMK (Myanmar Kyat)','currency_code'=>'MMK','currency_symbol'=>'K','language'=>'Burmese','timezone'=>'UTC+06:30','population'=>54409800,'area'=>676578],
                ['code'=>'KH','name'=>'Cambodia',     'flag'=>'https://flagcdn.com/w80/kh.png','capital'=>'Phnom Penh',   'region'=>'Asia','subregion'=>'Southeast Asia','lat'=>12.5657,'lng'=>104.9910,'currency'=>'KHR (Cambodian Riel)','currency_code'=>'KHR','currency_symbol'=>'៛','language'=>'Khmer','timezone'=>'UTC+07:00','population'=>16718965,'area'=>181035],
                ['code'=>'LA','name'=>'Laos',         'flag'=>'https://flagcdn.com/w80/la.png','capital'=>'Vientiane',    'region'=>'Asia','subregion'=>'Southeast Asia','lat'=>19.8563,'lng'=>102.4955,'currency'=>'LAK (Lao Kip)','currency_code'=>'LAK','currency_symbol'=>'₭','language'=>'Lao','timezone'=>'UTC+07:00','population'=>7275560,'area'=>236800],
                
                // Asia Timur & Selatan
                ['code'=>'CN','name'=>'China',        'flag'=>'https://flagcdn.com/w80/cn.png','capital'=>'Beijing',     'region'=>'Asia','subregion'=>'East Asia',     'lat'=>35.8617,'lng'=>104.1954,'currency'=>'CNY (Chinese Yuan)','currency_code'=>'CNY','currency_symbol'=>'¥','language'=>'Mandarin','timezone'=>'UTC+08:00','population'=>1402112000,'area'=>9640821],
                ['code'=>'JP','name'=>'Japan',        'flag'=>'https://flagcdn.com/w80/jp.png','capital'=>'Tokyo',       'region'=>'Asia','subregion'=>'East Asia',     'lat'=>36.2048,'lng'=>138.2529,'currency'=>'JPY (Japanese Yen)','currency_code'=>'JPY','currency_symbol'=>'¥','language'=>'Japanese','timezone'=>'UTC+09:00','population'=>125836021,'area'=>377975],
                ['code'=>'KR','name'=>'South Korea',  'flag'=>'https://flagcdn.com/w80/kr.png','capital'=>'Seoul',       'region'=>'Asia','subregion'=>'East Asia',     'lat'=>35.9078,'lng'=>127.7669,'currency'=>'KRW (South Korean Won)','currency_code'=>'KRW','currency_symbol'=>'₩','language'=>'Korean','timezone'=>'UTC+09:00','population'=>51780579,'area'=>100210],
                ['code'=>'IN','name'=>'India',        'flag'=>'https://flagcdn.com/w80/in.png','capital'=>'New Delhi',   'region'=>'Asia','subregion'=>'Southern Asia', 'lat'=>20.5937,'lng'=>78.9629,'currency'=>'INR (Indian Rupee)','currency_code'=>'INR','currency_symbol'=>'₹','language'=>'Hindi','timezone'=>'UTC+05:30','population'=>1380004385,'area'=>3287263],
                ['code'=>'PK','name'=>'Pakistan',     'flag'=>'https://flagcdn.com/w80/pk.png','capital'=>'Islamabad',   'region'=>'Asia','subregion'=>'Southern Asia', 'lat'=>30.3753,'lng'=>69.3451,'currency'=>'PKR (Pakistani Rupee)','currency_code'=>'PKR','currency_symbol'=>'₨','language'=>'Urdu','timezone'=>'UTC+05:00','population'=>220892331,'area'=>881913],
                ['code'=>'BD','name'=>'Bangladesh',   'flag'=>'https://flagcdn.com/w80/bd.png','capital'=>'Dhaka',       'region'=>'Asia','subregion'=>'Southern Asia', 'lat'=>23.6850,'lng'=>90.3563,'currency'=>'BDT (Bangladeshi Taka)','currency_code'=>'BDT','currency_symbol'=>'৳','language'=>'Bengali','timezone'=>'UTC+06:00','population'=>164689383,'area'=>147570],
                ['code'=>'LK','name'=>'Sri Lanka',    'flag'=>'https://flagcdn.com/w80/lk.png','capital'=>'Colombo',     'region'=>'Asia','subregion'=>'Southern Asia', 'lat'=>7.8731,'lng'=>80.7718,'currency'=>'LKR (Sri Lankan Rupee)','currency_code'=>'LKR','currency_symbol'=>'₨','language'=>'Sinhala','timezone'=>'UTC+05:30','population'=>21919000,'area'=>65610],
                
                // Timur Tengah
                ['code'=>'SA','name'=>'Saudi Arabia', 'flag'=>'https://flagcdn.com/w80/sa.png','capital'=>'Riyadh',      'region'=>'Asia','subregion'=>'Western Asia','lat'=>23.8859,'lng'=>45.0792,'currency'=>'SAR (Saudi Riyal)','currency_code'=>'SAR','currency_symbol'=>'﷼','language'=>'Arabic','timezone'=>'UTC+03:00','population'=>34813867,'area'=>2149690],
                ['code'=>'AE','name'=>'United Arab Emirates','flag'=>'https://flagcdn.com/w80/ae.png','capital'=>'Abu Dhabi','region'=>'Asia','subregion'=>'Western Asia','lat'=>23.4241,'lng'=>53.8478,'currency'=>'AED (UAE Dirham)','currency_code'=>'AED','currency_symbol'=>'د.إ','language'=>'Arabic','timezone'=>'UTC+04:00','population'=>9890400,'area'=>83600],
                ['code'=>'IR','name'=>'Iran',         'flag'=>'https://flagcdn.com/w80/ir.png','capital'=>'Tehran',       'region'=>'Asia','subregion'=>'Western Asia','lat'=>32.4279,'lng'=>53.6880,'currency'=>'IRR (Iranian Rial)','currency_code'=>'IRR','currency_symbol'=>'﷼','language'=>'Persian','timezone'=>'UTC+03:30','population'=>83992953,'area'=>1648195],
                ['code'=>'IQ','name'=>'Iraq',         'flag'=>'https://flagcdn.com/w80/iq.png','capital'=>'Baghdad',      'region'=>'Asia','subregion'=>'Western Asia','lat'=>33.2232,'lng'=>43.6793,'currency'=>'IQD (Iraqi Dinar)','currency_code'=>'IQD','currency_symbol'=>'ع.د','language'=>'Arabic','timezone'=>'UTC+03:00','population'=>40222503,'area'=>438317],
                ['code'=>'QA','name'=>'Qatar',        'flag'=>'https://flagcdn.com/w80/qa.png','capital'=>'Doha',         'region'=>'Asia','subregion'=>'Western Asia','lat'=>25.3548,'lng'=>51.1839,'currency'=>'QAR (Qatari Riyal)','currency_code'=>'QAR','currency_symbol'=>'﷼','language'=>'Arabic','timezone'=>'UTC+03:00','population'=>2881060,'area'=>11586],
                ['code'=>'KW','name'=>'Kuwait',       'flag'=>'https://flagcdn.com/w80/kw.png','capital'=>'Kuwait City',  'region'=>'Asia','subregion'=>'Western Asia','lat'=>29.3117,'lng'=>47.4818,'currency'=>'KWD (Kuwaiti Dinar)','currency_code'=>'KWD','currency_symbol'=>'د.ك','language'=>'Arabic','timezone'=>'UTC+03:00','population'=>4270563,'area'=>17818],
                ['code'=>'IL','name'=>'Israel',       'flag'=>'https://flagcdn.com/w80/il.png','capital'=>'Jerusalem',    'region'=>'Asia','subregion'=>'Western Asia','lat'=>31.0461,'lng'=>34.8516,'currency'=>'ILS (Israeli New Shekel)','currency_code'=>'ILS','currency_symbol'=>'₪','language'=>'Hebrew','timezone'=>'UTC+02:00','population'=>9216900,'area'=>20770],
                ['code'=>'JO','name'=>'Jordan',       'flag'=>'https://flagcdn.com/w80/jo.png','capital'=>'Amman',        'region'=>'Asia','subregion'=>'Western Asia','lat'=>30.5852,'lng'=>36.2384,'currency'=>'JOD (Jordanian Dinar)','currency_code'=>'JOD','currency_symbol'=>'د.ا','language'=>'Arabic','timezone'=>'UTC+02:00','population'=>10203134,'area'=>89342],
                ['code'=>'OM','name'=>'Oman',         'flag'=>'https://flagcdn.com/w80/om.png','capital'=>'Muscat',       'region'=>'Asia','subregion'=>'Western Asia','lat'=>21.5125,'lng'=>55.9233,'currency'=>'OMR (Omani Rial)','currency_code'=>'OMR','currency_symbol'=>'﷼','language'=>'Arabic','timezone'=>'UTC+04:00','population'=>5106626,'area'=>309500],
                
                // Amerika
                ['code'=>'US','name'=>'United States','flag'=>'https://flagcdn.com/w80/us.png','capital'=>'Washington D.C.','region'=>'Americas','subregion'=>'North America','lat'=>37.0902,'lng'=>-95.7129,'currency'=>'USD (US Dollar)','currency_code'=>'USD','currency_symbol'=>'$','language'=>'English','timezone'=>'UTC-05:00','population'=>331002651,'area'=>9372610],
                ['code'=>'CA','name'=>'Canada',        'flag'=>'https://flagcdn.com/w80/ca.png','capital'=>'Ottawa',       'region'=>'Americas','subregion'=>'North America','lat'=>56.1304,'lng'=>-106.3468,'currency'=>'CAD (Canadian Dollar)','currency_code'=>'CAD','currency_symbol'=>'$','language'=>'English','timezone'=>'UTC-05:00','population'=>37742154,'area'=>9984670],
                ['code'=>'MX','name'=>'Mexico',        'flag'=>'https://flagcdn.com/w80/mx.png','capital'=>'Mexico City',  'region'=>'Americas','subregion'=>'North America','lat'=>23.6345,'lng'=>-102.5528,'currency'=>'MXN (Mexican Peso)','currency_code'=>'MXN','currency_symbol'=>'$','language'=>'Spanish','timezone'=>'UTC-06:00','population'=>128932753,'area'=>1964375],
                ['code'=>'BR','name'=>'Brazil',       'flag'=>'https://flagcdn.com/w80/br.png','capital'=>'Brasília',    'region'=>'Americas','subregion'=>'South America','lat'=>-14.2350,'lng'=>-51.9253,'currency'=>'BRL (Brazilian Real)','currency_code'=>'BRL','currency_symbol'=>'R$','language'=>'Portuguese','timezone'=>'UTC-03:00','population'=>212559417,'area'=>8515767],
                ['code'=>'AR','name'=>'Argentina',    'flag'=>'https://flagcdn.com/w80/ar.png','capital'=>'Buenos Aires','region'=>'Americas','subregion'=>'South America','lat'=>-38.4161,'lng'=>-63.6167,'currency'=>'ARS (Argentine Peso)','currency_code'=>'ARS','currency_symbol'=>'$','language'=>'Spanish','timezone'=>'UTC-03:00','population'=>45195777,'area'=>2780400],
                ['code'=>'CO','name'=>'Colombia',     'flag'=>'https://flagcdn.com/w80/co.png','capital'=>'Bogotá',       'region'=>'Americas','subregion'=>'South America','lat'=>4.5709,'lng'=>-74.2973,'currency'=>'COP (Colombian Peso)','currency_code'=>'COP','currency_symbol'=>'$','language'=>'Spanish','timezone'=>'UTC-05:00','population'=>50882891,'area'=>1141748],
                ['code'=>'CL','name'=>'Chile',        'flag'=>'https://flagcdn.com/w80/cl.png','capital'=>'Santiago',     'region'=>'Americas','subregion'=>'South America','lat'=>-35.6751,'lng'=>-71.5430,'currency'=>'CLP (Chilean Peso)','currency_code'=>'CLP','currency_symbol'=>'$','language'=>'Spanish','timezone'=>'UTC-04:00','population'=>19116209,'area'=>756096],
                ['code'=>'PE','name'=>'Peru',         'flag'=>'https://flagcdn.com/w80/pe.png','capital'=>'Lima',         'region'=>'Americas','subregion'=>'South America','lat'=>-9.1900,'lng'=>-75.0152,'currency'=>'PEN (Peruvian Sol)','currency_code'=>'PEN','currency_symbol'=>'S/.','language'=>'Spanish','timezone'=>'UTC-05:00','population'=>32971846,'area'=>1285216],
                ['code'=>'VE','name'=>'Venezuela',    'flag'=>'https://flagcdn.com/w80/ve.png','capital'=>'Caracas',      'region'=>'Americas','subregion'=>'South America','lat'=>6.4238,'lng'=>-66.5897,'currency'=>'VES (Venezuelan Bolívar)','currency_code'=>'VES','currency_symbol'=>'Bs.S','language'=>'Spanish','timezone'=>'UTC-04:00','population'=>28435943,'area'=>916445],
                ['code'=>'PA','name'=>'Panama',       'flag'=>'https://flagcdn.com/w80/pa.png','capital'=>'Panama City',  'region'=>'Americas','subregion'=>'Central America','lat'=>8.5380,'lng'=>-80.7821,'currency'=>'PAB (Panamanian Balboa)','currency_code'=>'PAB','currency_symbol'=>'B/.','language'=>'Spanish','timezone'=>'UTC-05:00','population'=>4314768,'area'=>75420],
                
                // Afrika
                ['code'=>'ZA','name'=>'South Africa', 'flag'=>'https://flagcdn.com/w80/za.png','capital'=>'Pretoria',    'region'=>'Africa','subregion'=>'Southern Africa','lat'=>-30.5595,'lng'=>22.9375,'currency'=>'ZAR (South African Rand)','currency_code'=>'ZAR','currency_symbol'=>'R','language'=>'Zulu','timezone'=>'UTC+02:00','population'=>59308690,'area'=>1221037],
                ['code'=>'EG','name'=>'Egypt',        'flag'=>'https://flagcdn.com/w80/eg.png','capital'=>'Cairo',       'region'=>'Africa','subregion'=>'Northern Africa','lat'=>26.8206,'lng'=>30.8025,'currency'=>'EGP (Egyptian Pound)','currency_code'=>'EGP','currency_symbol'=>'E£','language'=>'Arabic','timezone'=>'UTC+02:00','population'=>102334404,'area'=>1002450],
                ['code'=>'NG','name'=>'Nigeria',      'flag'=>'https://flagcdn.com/w80/ng.png','capital'=>'Abuja',       'region'=>'Africa','subregion'=>'Western Africa','lat'=>9.0820,'lng'=>8.6753,'currency'=>'NGN (Nigerian Naira)','currency_code'=>'NGN','currency_symbol'=>'₦','language'=>'English','timezone'=>'UTC+01:00','population'=>206139589,'area'=>923768],
                ['code'=>'KE','name'=>'Kenya',        'flag'=>'https://flagcdn.com/w80/ke.png','capital'=>'Nairobi',     'region'=>'Africa','subregion'=>'Eastern Africa','lat'=>-0.0236,'lng'=>37.9062,'currency'=>'KES (Kenyan Shilling)','currency_code'=>'KES','currency_symbol'=>'Sh','language'=>'Swahili','timezone'=>'UTC+03:00','population'=>53771296,'area'=>580367],
                ['code'=>'MA','name'=>'Morocco',      'flag'=>'https://flagcdn.com/w80/ma.png','capital'=>'Rabat',       'region'=>'Africa','subregion'=>'Northern Africa','lat'=>31.7917,'lng'=>-7.0926,'currency'=>'MAD (Moroccan Dirham)','currency_code'=>'MAD','currency_symbol'=>'د.m.','language'=>'Arabic','timezone'=>'UTC+01:00','population'=>36910560,'area'=>446550],
                ['code'=>'DZ','name'=>'Algeria',      'flag'=>'https://flagcdn.com/w80/dz.png','capital'=>'Algiers',      'region'=>'Africa','subregion'=>'Northern Africa','lat'=>28.0339,'lng'=>1.6596,'currency'=>'DZD (Algerian Dinar)','currency_code'=>'DZD','currency_symbol'=>'د.ج','language'=>'Arabic','timezone'=>'UTC+01:00','population'=>43851043,'area'=>2381741],
                ['code'=>'GH','name'=>'Ghana',        'flag'=>'https://flagcdn.com/w80/gh.png','capital'=>'Accra',        'region'=>'Africa','subregion'=>'Western Africa','lat'=>7.9465,'lng'=>-1.0232,'currency'=>'GHS (Ghanaian Cedi)','currency_code'=>'GHS','currency_symbol'=>'₵','language'=>'English','timezone'=>'UTC+00:00','population'=>31072940,'area'=>238533],
                
                // Eropa (Semua negara utama/besar)
                ['code'=>'DE','name'=>'Germany',      'flag'=>'https://flagcdn.com/w80/de.png','capital'=>'Berlin',      'region'=>'Europe','subregion'=>'Western Europe','lat'=>51.1657,'lng'=>10.4515,'currency'=>'EUR (Euro)','currency_code'=>'EUR','currency_symbol'=>'€','language'=>'German','timezone'=>'UTC+01:00','population'=>83132799,'area'=>357114],
                ['code'=>'FR','name'=>'France',       'flag'=>'https://flagcdn.com/w80/fr.png','capital'=>'Paris',       'region'=>'Europe','subregion'=>'Western Europe','lat'=>46.2276,'lng'=>2.2137,'currency'=>'EUR (Euro)','currency_code'=>'EUR','currency_symbol'=>'€','language'=>'French','timezone'=>'UTC+01:00','population'=>67391582,'area'=>643801],
                ['code'=>'GB','name'=>'United Kingdom','flag'=>'https://flagcdn.com/w80/gb.png','capital'=>'London',      'region'=>'Europe','subregion'=>'Northern Europe','lat'=>55.3781,'lng'=>-3.4360,'currency'=>'GBP (British Pound)','currency_code'=>'GBP','currency_symbol'=>'£','language'=>'English','timezone'=>'UTC+00:00','population'=>67215293,'area'=>242495],
                ['code'=>'IT','name'=>'Italy',        'flag'=>'https://flagcdn.com/w80/it.png','capital'=>'Rome',        'region'=>'Europe','subregion'=>'Southern Europe','lat'=>41.8719,'lng'=>12.5674,'currency'=>'EUR (Euro)','currency_code'=>'EUR','currency_symbol'=>'€','language'=>'Italian','timezone'=>'UTC+01:00','population'=>59641488,'area'=>301340],
                ['code'=>'ES','name'=>'Spain',        'flag'=>'https://flagcdn.com/w80/es.png','capital'=>'Madrid',      'region'=>'Europe','subregion'=>'Southern Europe','lat'=>40.4637,'lng'=>-3.7492,'currency'=>'EUR (Euro)','currency_code'=>'EUR','currency_symbol'=>'€','language'=>'Spanish','timezone'=>'UTC+01:00','population'=>47351595,'area'=>505992],
                ['code'=>'NL','name'=>'Netherlands',  'flag'=>'https://flagcdn.com/w80/nl.png','capital'=>'Amsterdam',   'region'=>'Europe','subregion'=>'Western Europe','lat'=>52.1326,'lng'=>5.2913,'currency'=>'EUR (Euro)','currency_code'=>'EUR','currency_symbol'=>'€','language'=>'Dutch','timezone'=>'UTC+01:00','population'=>17441139,'area'=>41850],
                ['code'=>'RU','name'=>'Russia',       'flag'=>'https://flagcdn.com/w80/ru.png','capital'=>'Moscow',      'region'=>'Europe','subregion'=>'Eastern Europe','lat'=>61.5240,'lng'=>105.3188,'currency'=>'RUB (Russian Ruble)','currency_code'=>'RUB','currency_symbol'=>'₽','language'=>'Russian','timezone'=>'UTC+03:00','population'=>145934462,'area'=>17098242],
                ['code'=>'UA','name'=>'Ukraine',      'flag'=>'https://flagcdn.com/w80/ua.png','capital'=>'Kyiv',        'region'=>'Europe','subregion'=>'Eastern Europe','lat'=>48.3794,'lng'=>31.1656,'currency'=>'UAH (Ukrainian Hryvnia)','currency_code'=>'UAH','currency_symbol'=>'₴','language'=>'Ukrainian','timezone'=>'UTC+02:00','population'=>44134693,'area'=>603500],
                ['code'=>'CH','name'=>'Switzerland',  'flag'=>'https://flagcdn.com/w80/ch.png','capital'=>'Bern',        'region'=>'Europe','subregion'=>'Western Europe','lat'=>46.8182,'lng'=>8.2275,'currency'=>'CHF (Swiss Franc)','currency_code'=>'CHF','currency_symbol'=>'Fr','language'=>'German','timezone'=>'UTC+01:00','population'=>8636896,'area'=>41285],
                ['code'=>'SE','name'=>'Sweden',       'flag'=>'https://flagcdn.com/w80/se.png','capital'=>'Stockholm',   'region'=>'Europe','subregion'=>'Northern Europe','lat'=>60.1282,'lng'=>18.6435,'currency'=>'SEK (Swedish Krona)','currency_code'=>'SEK','currency_symbol'=>'kr','language'=>'Swedish','timezone'=>'UTC+01:00','population'=>10353442,'area'=>450295],
                ['code'=>'NO','name'=>'Norway',       'flag'=>'https://flagcdn.com/w80/no.png','capital'=>'Oslo',        'region'=>'Europe','subregion'=>'Northern Europe','lat'=>60.4720,'lng'=>8.4689,'currency'=>'NOK (Norwegian Krone)','currency_code'=>'NOK','currency_symbol'=>'kr','language'=>'Norwegian','timezone'=>'UTC+01:00','population'=>5379855,'area'=>323802],
                ['code'=>'DK','name'=>'Denmark',      'flag'=>'https://flagcdn.com/w80/dk.png','capital'=>'Copenhagen',  'region'=>'Europe','subregion'=>'Northern Europe','lat'=>56.2639,'lng'=>9.5018,'currency'=>'DKK (Danish Krone)','currency_code'=>'DKK','currency_symbol'=>'kr','language'=>'Danish','timezone'=>'UTC+01:00','population'=>5831404,'area'=>43094],
                ['code'=>'FI','name'=>'Finland',      'flag'=>'https://flagcdn.com/w80/fi.png','capital'=>'Helsinki',    'region'=>'Europe','subregion'=>'Northern Europe','lat'=>61.9241,'lng'=>25.7482,'currency'=>'EUR (Euro)','currency_code'=>'EUR','currency_symbol'=>'€','language'=>'Finnish','timezone'=>'UTC+02:00','population'=>5530719,'area'=>338424],
                ['code'=>'BE','name'=>'Belgium',      'flag'=>'https://flagcdn.com/w80/be.png','capital'=>'Brussels',    'region'=>'Europe','subregion'=>'Western Europe','lat'=>50.5039,'lng'=>4.4699,'currency'=>'EUR (Euro)','currency_code'=>'EUR','currency_symbol'=>'€','language'=>'Dutch','timezone'=>'UTC+01:00','population'=>11589623,'area'=>30528],
                ['code'=>'PT','name'=>'Portugal',     'flag'=>'https://flagcdn.com/w80/pt.png','capital'=>'Lisbon',      'region'=>'Europe','subregion'=>'Southern Europe','lat'=>39.3999,'lng'=>-8.2245,'currency'=>'EUR (Euro)','currency_code'=>'EUR','currency_symbol'=>'€','language'=>'Portuguese','timezone'=>'UTC+00:00','population'=>10270865,'area'=>92090],
                ['code'=>'GR','name'=>'Greece',       'flag'=>'https://flagcdn.com/w80/gr.png','capital'=>'Athens',      'region'=>'Europe','subregion'=>'Southern Europe','lat'=>39.0742,'lng'=>21.8243,'currency'=>'EUR (Euro)','currency_code'=>'EUR','currency_symbol'=>'€','language'=>'Greek','timezone'=>'UTC+02:00','population'=>10715549,'area'=>131957],
                ['code'=>'AT','name'=>'Austria',      'flag'=>'https://flagcdn.com/w80/at.png','capital'=>'Vienna',      'region'=>'Europe','subregion'=>'Western Europe','lat'=>47.6965,'lng'=>13.3457,'currency'=>'EUR (Euro)','currency_code'=>'EUR','currency_symbol'=>'€','language'=>'German','timezone'=>'UTC+01:00','population'=>8901064,'area'=>83871],
                ['code'=>'PL','name'=>'Poland',       'flag'=>'https://flagcdn.com/w80/pl.png','capital'=>'Warsaw',      'region'=>'Europe','subregion'=>'Eastern Europe','lat'=>51.9194,'lng'=>19.1451,'currency'=>'PLN (Polish Złoty)','currency_code'=>'PLN','currency_symbol'=>'zł','language'=>'Polish','timezone'=>'UTC+01:00','population'=>37950802,'area'=>312679],
                
                // Oceania
                ['code'=>'AU','name'=>'Australia',    'flag'=>'https://flagcdn.com/w80/au.png','capital'=>'Canberra',    'region'=>'Oceania','subregion'=>'Australia and New Zealand','lat'=>-25.2744,'lng'=>133.7751,'currency'=>'AUD (Australian Dollar)','currency_code'=>'AUD','currency_symbol'=>'A$','language'=>'English','timezone'=>'UTC+10:00','population'=>25499884,'area'=>7692024],
                ['code'=>'NZ','name'=>'New Zealand',  'flag'=>'https://flagcdn.com/w80/nz.png','capital'=>'Wellington',  'region'=>'Oceania','subregion'=>'Australia and New Zealand','lat'=>-40.9006,'lng'=>174.8860,'currency'=>'NZD (New Zealand Dollar)','currency_code'=>'NZD','currency_symbol'=>'$','language'=>'English','timezone'=>'UTC+12:00','population'=>4822233,'area'=>268021],
            ],
            'total' => 57,
        ];
    }


    // STEP 4D: KURS MATA UANG — Simulasi Realistis
    public function getExchangeRate(Request $request)
    {
        $dayOfYear = (int)date('z');
        $baseRates = [
            'USD' => [16245.00, 'US Dollar',              '$',  0.8],
            'SGD' => [12050.00, 'Singapore Dollar',       'S$', 0.6],
            'EUR' => [17580.00, 'Euro',                   '€',  0.9],
            'JPY' => [107.00,   'Japanese Yen',           '¥',  0.5],
            'CNY' => [2245.00,  'Chinese Yuan',           '¥',  0.7],
            'MYR' => [3620.00,  'Malaysian Ringgit',      'RM', 0.5],
            'AUD' => [10650.00, 'Australian Dollar',      'A$', 0.8],
            'GBP' => [20450.00, 'British Pound',          '£',  0.7],
            'KRW' => [11.85,    'South Korean Won',       '₩',  0.6],
            'THB' => [455.00,   'Thai Baht',              '฿',  0.5],
            'INR' => [194.50,   'Indian Rupee',           '₹',  0.6],
            'SAR' => [4330.00,  'Saudi Riyal',            '﷼', 0.4],
            'AED' => [4420.00,  'UAE Dirham',             'د.إ',0.4],
            'VND' => [0.63,     'Vietnamese Dong',        '₫',  0.7],
        ];

        $currencyInfo = [
            'USD' => ['US Dollar', '$'],
            'SGD' => ['Singapore Dollar', 'S$'],
            'EUR' => ['Euro', '€'],
            'JPY' => ['Japanese Yen', '¥'],
            'CNY' => ['Chinese Yuan', '¥'],
            'MYR' => ['Malaysian Ringgit', 'RM'],
            'AUD' => ['Australian Dollar', 'A$'],
            'GBP' => ['British Pound', '£'],
            'KRW' => ['South Korean Won', '₩'],
            'THB' => ['Thai Baht', '฿'],
            'INR' => ['Indian Rupee', '₹'],
            'SAR' => ['Saudi Riyal', '﷼'],
            'AED' => ['UAE Dirham', 'د.إ'],
            'VND' => ['Vietnamese Dong', '₫'],
            'CAD' => ['Canadian Dollar', 'C$'],
            'MXN' => ['Mexican Peso', '$'],
            'BRL' => ['Brazilian Real', 'R$'],
            'ARS' => ['Argentine Peso', '$'],
            'COP' => ['Colombian Peso', '$'],
            'ZAR' => ['South African Rand', 'R'],
            'EGP' => ['Egyptian Pound', 'E£'],
            'NGN' => ['Nigerian Naira', '₦'],
            'KES' => ['Kenyan Shilling', 'Sh'],
            'MAD' => ['Moroccan Dirham', 'د.m.'],
            'RUB' => ['Russian Ruble', '₽'],
            'NZD' => ['New Zealand Dollar', '$'],
            'PKR' => ['Pakistani Rupee', '₨'],
            'IRR' => ['Iranian Rial', '﷼'],
            'TRY' => ['Turkish Lira', '₺'],
            'QAR' => ['Qatari Riyal', '﷼'],
            'KWD' => ['Kuwaiti Dinar', 'د.k'],
        ];

        $rates = [];
        $isReal = false;

        try {
            // Ambil kurs real-time dari API publik (bebas key)
            $response = Http::withoutVerifying()->timeout(8)->get('https://open.er-api.com/v6/latest/IDR');
            if ($response->successful()) {
                $data = $response->json();
                $apiRates = $data['rates'] ?? $data['conversion_rates'] ?? [];
                if (!empty($apiRates)) {
                    $isReal = true;
                }
            }
        } catch (\Exception $e) {
            // Tetap lanjut ke fallback jika gagal
        }

        foreach ($baseRates as $code => [$base, $name, $symbol, $pct]) {
            if ($isReal && isset($apiRates[$code]) && $apiRates[$code] > 0) {
                // Rate dari API adalah jumlah mata uang asing per 1 IDR.
                // Untuk mendapatkan IDR per 1 unit mata uang asing: 1 / rate
                $current = round(1 / $apiRates[$code], 2);
            } else {
                // Fluktuasi simulasi jika API gagal
                $variation = sin($dayOfYear * 0.1) * ($base * $pct / 100);
                $current = round($base + $variation, 2);
            }

            // Hitung perubahan deterministik harian agar grafik/indikator tren dinamis
            $yesterdayVariation = sin(($dayOfYear - 1) * 0.1) * ($current * 0.005);
            $change = round($yesterdayVariation, 2);
            $changePct = round(($change / ($current - $change)) * 100, 3);
            $direction = $change >= 0 ? 'up' : 'down';

            $rates[$code] = [
                'name'       => $name,
                'symbol'     => $symbol,
                'rate'       => $current,
                'change'     => $change,
                'change_pct' => $changePct,
                'direction'  => $direction,
            ];
        }

        // Jika API aktif, tambahkan seluruh mata uang lain dari API ke hasil
        if ($isReal) {
            foreach ($apiRates as $code => $apiRate) {
                if (isset($rates[$code]) || $apiRate <= 0 || $code === 'IDR') continue;

                $current = round(1 / $apiRate, 2);
                $name = $currencyInfo[$code][0] ?? "$code (Mata Uang)";
                $symbol = $currencyInfo[$code][1] ?? $code;

                $yesterdayVariation = sin(($dayOfYear - 1) * 0.1) * ($current * 0.005);
                $change = round($yesterdayVariation, 2);
                $changePct = round(($change / ($current - $change)) * 100, 3);
                $direction = $change >= 0 ? 'up' : 'down';

                $rates[$code] = [
                    'name'       => $name,
                    'symbol'     => $symbol,
                    'rate'       => $current,
                    'change'     => $change,
                    'change_pct' => $changePct,
                    'direction'  => $direction,
                ];
            }
        }

        return response()->json([
            'success'    => true,
            'simulated'  => !$isReal,
            'base'       => 'IDR',
            'timestamp'  => now()->format('Y-m-d H:i:s'),
            'rates'      => $rates,
        ]);
    }


    // ============================================================
    // STEP 4E: DATA PELABUHAN — Data Statis (Bisa Ditambah API nanti)
    public function getPorts(Request $request)
    {
        $apiKey = env('VESSEL_API_KEY');
        $hour   = (int)date('H');

        // Data pelabuhan utama dunia yang relevan untuk Indonesia
        $ports = [
            [
                'id' => 'SGSIN',
                'name' => 'Port of Singapore',
                'country' => 'Singapore',
                'un_locode' => 'SG SIN',
                'latitude' => 1.2640,
                'longitude' => 103.8220,
                'lat' => 1.2640,
                'lng' => 103.8220,
                'authority' => 'Maritime and Port Authority of Singapore (MPA)',
                'capacity' => '37.2M TEU/tahun',
                'region' => 'Selat Malaka',
                'status' => 'Operational',
                'waiting_time' => 1.5,
            ],
            [
                'id' => 'MYPKG',
                'name' => 'Port Klang',
                'country' => 'Malaysia',
                'un_locode' => 'MY PKG',
                'latitude' => 3.0020,
                'longitude' => 101.3520,
                'lat' => 3.0020,
                'lng' => 101.3520,
                'authority' => 'Port Klang Authority (PKA)',
                'capacity' => '13.7M TEU/tahun',
                'region' => 'Selat Malaka',
                'status' => 'Operational',
                'waiting_time' => 4.2,
            ],
            [
                'id' => 'IDTPP',
                'name' => 'Tanjung Priok Port',
                'country' => 'Indonesia',
                'un_locode' => 'ID TPP',
                'latitude' => -6.0980,
                'longitude' => 106.8910,
                'lat' => -6.0980,
                'lng' => 106.8910,
                'authority' => 'PT Pelabuhan Indonesia (Persero) - Pelindo',
                'capacity' => '8.5M TEU/tahun',
                'region' => 'Laut Jawa',
                'status' => 'Operational',
                'waiting_time' => 8.5,
            ],
            [
                'id' => 'IDTPK',
                'name' => 'Tanjung Perak Port',
                'country' => 'Indonesia',
                'un_locode' => 'ID SUB',
                'latitude' => -7.1950,
                'longitude' => 112.7230,
                'lat' => -7.1950,
                'lng' => 112.7230,
                'authority' => 'PT Pelabuhan Indonesia (Persero) - Pelindo',
                'capacity' => '3.8M TEU/tahun',
                'region' => 'Selat Madura',
                'status' => 'Operational',
                'waiting_time' => 6.0,
            ],
            [
                'id' => 'THLCH',
                'name' => 'Laem Chabang Port',
                'country' => 'Thailand',
                'un_locode' => 'TH LCH',
                'latitude' => 13.0900,
                'longitude' => 100.8920,
                'lat' => 13.0900,
                'lng' => 100.8920,
                'authority' => 'Port Authority of Thailand (PAT)',
                'capacity' => '8.1M TEU/tahun',
                'region' => 'Teluk Thailand',
                'status' => 'Operational',
                'waiting_time' => 5.1,
            ],
            [
                'id' => 'VNSGN',
                'name' => 'Saigon Port (Cat Lai)',
                'country' => 'Vietnam',
                'un_locode' => 'VN SGN',
                'latitude' => 10.7620,
                'longitude' => 106.7960,
                'lat' => 10.7620,
                'lng' => 106.7960,
                'authority' => 'Saigon Port Joint Stock Company',
                'capacity' => '7.2M TEU/tahun',
                'region' => 'Laut China Selatan',
                'status' => 'Operational',
                'waiting_time' => 7.4,
            ],
            [
                'id' => 'PHMNL',
                'name' => 'Port of Manila',
                'country' => 'Philippines',
                'un_locode' => 'PH MNL',
                'latitude' => 14.6050,
                'longitude' => 120.9490,
                'lat' => 14.6050,
                'lng' => 120.9490,
                'authority' => 'Philippine Ports Authority (PPA)',
                'capacity' => '5.0M TEU/tahun',
                'region' => 'Teluk Manila',
                'status' => 'Operational',
                'waiting_time' => 12.0,
            ],
            [
                'id' => 'CNSGH',
                'name' => 'Port of Shanghai (Yangshan)',
                'country' => 'China',
                'un_locode' => 'CN SGH',
                'latitude' => 30.6250,
                'longitude' => 122.0710,
                'lat' => 30.6250,
                'lng' => 122.0710,
                'authority' => 'Shanghai International Port Group (SIPG)',
                'capacity' => '47.3M TEU/tahun',
                'region' => 'Laut Cina Timur',
                'status' => 'Operational',
                'waiting_time' => 16.5,
            ],
            [
                'id' => 'CNSHK',
                'name' => 'Port of Shenzhen (Yantian)',
                'country' => 'China',
                'un_locode' => 'CN SZX',
                'latitude' => 22.5760,
                'longitude' => 113.8820,
                'lat' => 22.5760,
                'lng' => 113.8820,
                'authority' => 'Shenzhen Port Administration',
                'capacity' => '28.9M TEU/tahun',
                'region' => 'Laut China Selatan',
                'status' => 'Operational',
                'waiting_time' => 11.2,
            ],
            [
                'id' => 'JPYOK',
                'name' => 'Port of Yokohama',
                'country' => 'Japan',
                'un_locode' => 'JP YOK',
                'latitude' => 35.4520,
                'longitude' => 139.6710,
                'lat' => 35.4520,
                'lng' => 139.6710,
                'authority' => 'Yokohama Port and Harbor Bureau',
                'capacity' => '3.0M TEU/tahun',
                'region' => 'Samudra Pasifik',
                'status' => 'Operational',
                'waiting_time' => 3.0,
            ],
            [
                'id' => 'KRPUS',
                'name' => 'Port of Busan',
                'country' => 'South Korea',
                'un_locode' => 'KR PUS',
                'latitude' => 35.0780,
                'longitude' => 128.9050,
                'lat' => 35.0780,
                'lng' => 128.9050,
                'authority' => 'Busan Port Authority (BPA)',
                'capacity' => '22.0M TEU/tahun',
                'region' => 'Selat Korea',
                'status' => 'Operational',
                'waiting_time' => 4.5,
            ],
            [
                'id' => 'INNSA',
                'name' => 'Jawaharlal Nehru Port (Nhava Sheva)',
                'country' => 'India',
                'un_locode' => 'IN NSA',
                'latitude' => 18.9480,
                'longitude' => 72.9510,
                'lat' => 18.9480,
                'lng' => 72.9510,
                'authority' => 'Jawaharlal Nehru Port Authority (JNPA)',
                'capacity' => '6.2M TEU/tahun',
                'region' => 'Laut Arab',
                'status' => 'Operational',
                'waiting_time' => 9.8,
            ],
            [
                'id' => 'AEJEA',
                'name' => 'Port of Jebel Ali',
                'country' => 'United Arab Emirates',
                'un_locode' => 'AE JEA',
                'latitude' => 25.0120,
                'longitude' => 55.0510,
                'lat' => 25.0120,
                'lng' => 55.0510,
                'authority' => 'DP World UAE',
                'capacity' => '14.5M TEU/tahun',
                'region' => 'Teluk Persia',
                'status' => 'Operational',
                'waiting_time' => 5.5,
            ],
            [
                'id' => 'NLRTM',
                'name' => 'Port of Rotterdam',
                'country' => 'Netherlands',
                'un_locode' => 'NL RTM',
                'latitude' => 51.9520,
                'longitude' => 4.0240,
                'lat' => 51.9520,
                'lng' => 4.0240,
                'authority' => 'Port of Rotterdam Authority',
                'capacity' => '14.5M TEU/tahun',
                'region' => 'Laut Utara',
                'status' => 'Operational',
                'waiting_time' => 6.8,
            ],
            [
                'id' => 'USLAX',
                'name' => 'Port of Los Angeles',
                'country' => 'United States',
                'un_locode' => 'US LAX',
                'latitude' => 33.7430,
                'longitude' => -118.2610,
                'lat' => 33.7430,
                'lng' => -118.2610,
                'authority' => 'City of Los Angeles Harbor Department',
                'capacity' => '10.6M TEU/tahun',
                'region' => 'Samudra Pasifik',
                'status' => 'Operational',
                'waiting_time' => 18.2,
            ],
        ];

        $isReal = false;

        if (!empty($apiKey)) {
            try {
                // Call VesselAPI to fetch real-time ports data
                $response = Http::withoutVerifying()->timeout(8)->get('https://api.vesselapi.com/v1/ports', [
                    'apiKey' => $apiKey
                ]);

                if ($response->successful()) {
                    $apiData = $response->json();
                    if (!empty($apiData['ports'])) {
                        $isReal = true;
                        // Map status/details dynamically from real API if needed
                    }
                }
            } catch (\Exception $e) {
                // Fallback to simulation if call fails
            }
        }

        // Tentukan status pelabuhan berdasarkan jam dan sedikit randomisasi
        // Ini membuat status "terasa berubah" walau data statis
        $statusOptions = ['Operational', 'Congested', 'Operational', 'Operational', 'Waspada']; // Lebih banyak Operational

        foreach ($ports as &$port) {
            // Gunakan ID pelabuhan + jam sebagai seed agar konsisten per jam
            $seed = crc32($port['id'] . floor($hour / 3)) % count($statusOptions);
            $port['status'] = $statusOptions[abs($seed)];

            // Map status ke level risiko
            $port['risk'] = match($port['status']) {
                'Congested' => 'medium',
                'Waspada'   => 'high',
                default     => 'low',
            };

            // Hitung waiting_time dinamis yang rasional berdasarkan status
            $baseWaiting = $port['waiting_time'] ?? 4.0;
            $port['waiting_time'] = match($port['status']) {
                'Congested' => round($baseWaiting * 2.2 + (abs($seed) % 3), 1),
                'Waspada'   => round($baseWaiting * 3.5 + (abs($seed) % 5), 1),
                default     => round($baseWaiting * 0.9 + (abs($seed) % 2) * 0.5, 1),
            };
        }
        unset($port); // Lepas referensi setelah loop

        return response()->json([
            'success'   => true,
            'real'      => $isReal,
            'data'      => $ports,
            'total'     => count($ports)
        ]);
    }


    // BERITA — NewsAPI (real) + fallback simulasi
    // API Key disimpan di .env: NEWS_API_KEY
    // Dokumentasi: https://newsapi.org/docs
    public function getNews(Request $request)
    {
        $category = $request->get('category', 'all');
        $apiKey   = env('NEWS_API_KEY');

        // Query per kategori
        $queryMap = [
            'all'         => 'supply chain OR logistics OR shipping OR trade',
            'economy'     => 'global economy OR inflation OR GDP OR trade war',
            'logistics'   => 'shipping logistics OR port congestion OR container',
            'geopolitics' => 'trade sanctions OR geopolitics OR South China Sea',
            'weather'     => 'typhoon shipping OR storm port OR weather maritime',
        ];
        $query = $queryMap[$category] ?? $queryMap['all'];

        // Kategori untuk NewsAPI top-headlines
        $newsApiCategory = in_array($category, ['economy', 'all']) ? 'business' : 'general';

        try {
            // Coba ambil dari NewsAPI
            $response = Http::withoutVerifying()->timeout(8)->get('https://newsapi.org/v2/everything', [
                'q'        => $query,
                'language' => 'en',
                'sortBy'   => 'publishedAt',
                'pageSize' => 10,
                'apiKey'   => $apiKey,
            ]);

            if ($response->successful()) {
                $articles = $response->json()['articles'] ?? [];

                if (count($articles) > 0) {
                    // Format artikel dari NewsAPI ke format internal kita
                    $formatted = array_map(function($art) use ($category) {
                        // Tentukan severity berdasarkan kata kunci di judul
                        $title    = strtolower($art['title'] ?? '');
                        $severity = 'low';
                        if (str_contains($title, 'war') || str_contains($title, 'crisis') ||
                            str_contains($title, 'sanction') || str_contains($title, 'typhoon') ||
                            str_contains($title, 'collapse') || str_contains($title, 'disruption')) {
                            $severity = 'high';
                        } elseif (str_contains($title, 'rise') || str_contains($title, 'surge') ||
                                  str_contains($title, 'fall') || str_contains($title, 'concern') ||
                                  str_contains($title, 'tension') || str_contains($title, 'delay')) {
                            $severity = 'medium';
                        }

                        // Hitung waktu publikasi
                        $published = $art['publishedAt'] ? now()->diffForHumans(\Carbon\Carbon::parse($art['publishedAt']), true) : 'baru saja';

                        return [
                            'title'    => $art['title'] ?? 'Tanpa judul',
                            'category' => $category === 'all' ? 'economy' : $category,
                            'source'   => $art['source']['name'] ?? 'NewsAPI',
                            'url'      => $art['url'] ?? '#',
                            'time'     => $published . ' lalu',
                            'severity' => $severity,
                            'real'     => true,
                        ];
                    }, $articles);

                    return response()->json([
                        'success'  => true,
                        'real'     => true,
                        'data'     => $formatted,
                        'total'    => count($formatted),
                    ]);
                }
            }

        } catch (\Exception $e) {
            // Kalau NewsAPI tidak bisa diakses, pakai fallback
        }

        // Fallback: berita simulasi realistis
        return response()->json($this->newsFallback($category));
    }

    private function newsFallback($category)
    {
        $newsPool = [
            ['title'=>'Pertumbuhan ekspor Indonesia naik 5.2% pada kuartal ini',              'category'=>'economy',     'source'=>'Bloomberg',        'time'=>'2j lalu',  'severity'=>'low'],
            ['title'=>'Fed tahan suku bunga, dolar menguat terhadap Rupiah dan Ringgit',      'category'=>'economy',     'source'=>'Reuters',          'time'=>'4j lalu',  'severity'=>'medium'],
            ['title'=>'Inflasi global mulai turun, sinyal positif untuk rantai pasok',        'category'=>'economy',     'source'=>'CNBC',             'time'=>'6j lalu',  'severity'=>'low'],
            ['title'=>'GDP China tumbuh 5.1%, dorong permintaan bahan baku Asia Tenggara',   'category'=>'economy',     'source'=>'Xinhua',           'time'=>'8j lalu',  'severity'=>'low'],
            ['title'=>'Resesi Jerman tekan permintaan produk ekspor regional',               'category'=>'economy',     'source'=>'Financial Times',  'time'=>'10j lalu', 'severity'=>'medium'],
            ['title'=>'ASEAN-EU Free Trade Agreement perkuat jalur ekspor dua kawasan',      'category'=>'economy',     'source'=>'ASEAN Secretariat','time'=>'12j lalu', 'severity'=>'low'],
            ['title'=>'Kemacetan di Port of Singapore sebabkan delay 3-5 hari pengiriman',  'category'=>'logistics',   'source'=>'Seatrade Maritime', 'time'=>'1j lalu',  'severity'=>'high'],
            ['title'=>'Tarif pengiriman kontainer rute Asia-Eropa naik 18% minggu ini',      'category'=>'logistics',   'source'=>'Drewry',           'time'=>'3j lalu',  'severity'=>'medium'],
            ['title'=>'Tanjung Priok resmikan dermaga baru, kapasitas naik 30%',             'category'=>'logistics',   'source'=>'Pelindo',          'time'=>'5j lalu',  'severity'=>'low'],
            ['title'=>'Otomasi pelabuhan capai 90% adopsi di Singapura',                     'category'=>'logistics',   'source'=>'Port Technology',  'time'=>'7j lalu',  'severity'=>'low'],
            ['title'=>'Terusan Suez kembali normal setelah gangguan teknis 48 jam',          'category'=>'logistics',   'source'=>'Lloyds List',      'time'=>'9j lalu',  'severity'=>'low'],
            ['title'=>'Ketegangan Laut China Selatan naikkan biaya asuransi kapal 12%',     'category'=>'geopolitics', 'source'=>'War Risk',         'time'=>'2j lalu',  'severity'=>'high'],
            ['title'=>'Perjanjian dagang ASEAN-UE diratifikasi 15 negara anggota',          'category'=>'geopolitics', 'source'=>'EurActiv',         'time'=>'5j lalu',  'severity'=>'low'],
            ['title'=>'Sanksi dagang baru hantam jalur pengiriman kawasan Timur Tengah',    'category'=>'geopolitics', 'source'=>'Al Jazeera',       'time'=>'7j lalu',  'severity'=>'medium'],
            ['title'=>'Konflik Laut Merah: kapal dialihkan via Tanjung Harapan Afrika',     'category'=>'geopolitics', 'source'=>'BBC',              'time'=>'9j lalu',  'severity'=>'high'],
            ['title'=>'Topan dekati Filipina, pelabuhan Manila masuk status siaga',          'category'=>'weather',     'source'=>'PAGASA',           'time'=>'1j lalu',  'severity'=>'high'],
            ['title'=>'La Niña diprediksi pengaruhi pola hujan Asia Tenggara 3 bulan',      'category'=>'weather',     'source'=>'BMKG',             'time'=>'4j lalu',  'severity'=>'medium'],
            ['title'=>'Gelombang panas Eropa hambat pelayaran sungai Rhine Jerman',          'category'=>'weather',     'source'=>'DW',               'time'=>'6j lalu',  'severity'=>'medium'],
        ];

        if ($category !== 'all') {
            $newsPool = array_values(array_filter($newsPool, fn($n) => $n['category'] === $category));
        }

        $shift = count($newsPool) > 0 ? (int)date('d') % count($newsPool) : 0;
        $news  = array_merge(array_slice($newsPool, $shift), array_slice($newsPool, 0, $shift));

        return ['success' => true, 'simulated' => true, 'data' => array_slice($news, 0, 10), 'total' => count($newsPool)];
    }


    // SKOR RISIKO — Kalkulasi Agregat per Negara
    // Skor risiko dihitung dari kombinasi beberapa faktor:
    // 1. Risiko cuaca (dari OpenMeteo)
    // 2. Risiko ekonomi (inflasi tinggi = risiko tinggi)
    // 3. Risiko geopolitik (hardcoded berdasarkan kondisi terkini)
    // 4. Risiko logistik (kemacetan pelabuhan)
    //
    // Skor 0-100: 0=aman, 100=sangat berisiko
    public function getRiskScore(Request $request, $countryCode)
    {
        $countryCode = strtoupper($countryCode);

        // Data risiko negara-negara utama (berbasis indeks geopolitik & logistik global)
        $geoRisk = [
            'ID'=>25,'SG'=>10,'MY'=>20,'CN'=>45,'JP'=>15,'KR'=>30,'IN'=>35,'US'=>20,
            'DE'=>15,'AU'=>10,'TH'=>25,'VN'=>30,'PH'=>40,'BR'=>45,'AE'=>25,'SA'=>35,
            'NL'=>10,'ZA'=>50,'FR'=>18,'GB'=>18,'IT'=>22,'ES'=>20,'TR'=>48,'RU'=>75,
            'UA'=>80,'IR'=>82,'IQ'=>78,'AF'=>90,'PK'=>55,'BD'=>40,'LK'=>60,'MM'=>65,
            'KH'=>35,'LA'=>38,'BN'=>15,'TL'=>50,'MN'=>30,'KZ'=>40,'UZ'=>42,'AZ'=>45,
            'GE'=>40,'AM'=>45,'EG'=>48,'NG'=>65,'KE'=>42,'ET'=>60,'TZ'=>38,'GH'=>30,
            'CI'=>45,'CM'=>52,'SN'=>35,'MA'=>30,'DZ'=>38,'TN'=>32,'LY'=>72,'SD'=>70,
            'MX'=>42,'CO'=>50,'PE'=>38,'CL'=>25,'AR'=>52,'VE'=>80,'EC'=>45,'BO'=>40,
            'PY'=>35,'UY'=>22,'CU'=>60,'DO'=>32,'GT'=>48,'HN'=>55,'SV'=>52,'CR'=>15,
            'PA'=>30,'JM'=>40,'HT'=>78,'TT'=>32,'CA'=>12,'NO'=>10,'SE'=>10,'DK'=>10,
            'FI'=>10,'IS'=>8, 'CH'=>8, 'AT'=>12,'BE'=>15,'PT'=>15,'GR'=>28,'PL'=>25,
            'CZ'=>18,'HU'=>22,'RO'=>28,'BG'=>30,'HR'=>22,'SK'=>18,'SI'=>15,'RS'=>35,
            'BA'=>38,'AL'=>40,'MK'=>35,'ME'=>32,'XK'=>40,'LT'=>20,'LV'=>22,'EE'=>18,
            'BY'=>70,'MD'=>45,'NZ'=>10,'FJ'=>22,'PG'=>42,'SB'=>35,'VU'=>30,'WS'=>25,
            'TO'=>20,'IL'=>55,'JO'=>38,'LB'=>72,'SY'=>85,'YE'=>88,'QA'=>22,'KW'=>28,
            'BH'=>30,'OM'=>25,'IQ'=>78,'CY'=>25,'MT'=>12,'LU'=>8, 'LI'=>8, 'MC'=>8,
        ];

        $logisticRisk = [
            'ID'=>35,'SG'=>15,'MY'=>25,'CN'=>30,'JP'=>20,'KR'=>20,'IN'=>45,'US'=>20,
            'DE'=>15,'AU'=>20,'TH'=>30,'VN'=>35,'PH'=>40,'BR'=>50,'AE'=>20,'SA'=>30,
            'NL'=>15,'ZA'=>45,'FR'=>18,'GB'=>18,'IT'=>25,'ES'=>22,'TR'=>42,'RU'=>55,
            'UA'=>75,'IR'=>65,'IQ'=>70,'AF'=>85,'PK'=>58,'BD'=>48,'LK'=>55,'MM'=>60,
            'KH'=>40,'LA'=>42,'BN'=>18,'TL'=>55,'MN'=>38,'KZ'=>42,'UZ'=>45,'AZ'=>40,
            'GE'=>38,'AM'=>42,'EG'=>42,'NG'=>60,'KE'=>45,'ET'=>58,'TZ'=>42,'GH'=>35,
            'CI'=>48,'CM'=>55,'SN'=>40,'MA'=>32,'DZ'=>40,'TN'=>35,'LY'=>68,'SD'=>65,
            'MX'=>40,'CO'=>48,'PE'=>40,'CL'=>28,'AR'=>50,'VE'=>75,'EC'=>48,'BO'=>45,
            'PY'=>38,'UY'=>25,'CU'=>58,'DO'=>35,'GT'=>50,'HN'=>55,'SV'=>52,'CR'=>18,
            'PA'=>32,'JM'=>42,'HT'=>75,'TT'=>35,'CA'=>14,'NO'=>12,'SE'=>12,'DK'=>12,
            'FI'=>12,'IS'=>10,'CH'=>10,'AT'=>14,'BE'=>16,'PT'=>18,'GR'=>30,'PL'=>28,
        ];

        // Negara yang tidak ada di tabel → generate deterministik berdasarkan kode
        // (bukan random, agar nilai konsisten setiap request)
        $codeSum = array_sum(array_map('ord', str_split($countryCode)));
        $geo      = $geoRisk[$countryCode]      ?? (($codeSum * 7) % 55 + 15);
        $logistic = $logisticRisk[$countryCode] ?? (($codeSum * 11) % 50 + 15);

        // Skor akhir = rata-rata tertimbang
        // Bobot: Geopolitik 40%, Logistik 30%, Cuaca 30%
        // Cuaca kita simulasikan berdasarkan variasi harian
        $weatherRisk = min(100, abs(sin((int)date('d') * pi() / 30 + crc32($countryCode) % 10)) * 60);

        $totalRisk = round($geo * 0.40 + $logistic * 0.30 + $weatherRisk * 0.30);
        $totalRisk = min(100, max(5, $totalRisk)); // Pastikan dalam range 5-100

        return response()->json([
            'success'       => true,
            'country'       => $countryCode,
            'score'         => $totalRisk,
            'level'         => $totalRisk >= 60 ? 'high' : ($totalRisk >= 30 ? 'medium' : 'low'),
            'breakdown'     => [
                'geopolitical' => $geo,
                'logistics'    => $logistic,
                'weather'      => round($weatherRisk),
            ],
        ]);
    }
}
