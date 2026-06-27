<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Global Supply Chain Monitor</title>
    <meta name="description" content="Login ke platform monitoring risiko rantai pasok global">

    {{-- Bootstrap Icons untuk ikon-ikon UI --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    {{-- CSS kita sendiri (sudah include Google Fonts Inter) --}}
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
</head>
<body>

    {{--
        .auth-page  → Wrapper full-screen dengan gradient gelap dan efek lingkaran dekoratif
        Didefinisikan di style.css, membuat latar belakang terasa hidup
    --}}
    <div class="auth-page">

        {{--
            .auth-card  → Card utama login dengan efek glassmorphism
            (kaca buram — background semi-transparan dengan blur)
        --}}
        <div class="auth-card">

            {{-- Logo & Judul Aplikasi --}}
            <div class="auth-logo">
                <div class="logo-icon">
                    {{-- Ikon kotak kargo sebagai logo --}}
                    <i class="bi bi-box-seam-fill" style="color:#fff;"></i>
                </div>
                <h4>Global Supply Chain</h4>
                <p>Platform pemantauan rantai pasok global</p>
            </div>

            {{--
                Menampilkan error jika ada (contoh: "email atau password salah")
                @error('login') adalah syntax Blade untuk menampilkan pesan error
                dari session validation Laravel
            --}}
            @error('login')
                <div class="auth-alert">
                    <i class="bi bi-exclamation-circle-fill"></i>
                    {{ $message }}
                </div>
            @enderror

            {{--
                Form Login
                action="/masuk"  → dikirim ke route POST /masuk di web.php
                method="post"    → menggunakan HTTP POST (bukan GET)
                @csrf            → token keamanan wajib di Laravel untuk mencegah serangan CSRF
            --}}
            <form action="/masuk" method="post">
                @csrf

                <div>
                    <label for="email" class="auth-form-label">
                        <i class="bi bi-envelope me-1"></i> Email
                    </label>
                    {{--
                        value="{{ old('email') }}" → mengisi ulang email jika form gagal validasi,
                        supaya user tidak perlu ketik ulang dari awal
                    --}}
                    <input
                        type="email"
                        id="email"
                        name="email"
                        class="auth-form-control @error('email') border-danger @enderror"
                        value="{{ old('email') }}"
                        placeholder="Masukkan email Anda"
                        required
                        autocomplete="email"
                    >
                    @error('email')
                        <div style="color:#ff8a80; font-size:11px; margin-top:-10px; margin-bottom:10px;">
                            <i class="bi bi-exclamation-circle me-1"></i>{{ $message }}
                        </div>
                    @enderror
                </div>

                <div>
                    <label for="password" class="auth-form-label">
                        <i class="bi bi-lock me-1"></i> Password
                    </label>
                    <div style="position:relative; margin-bottom: 16px;">
                        <input
                            type="password"
                            id="password"
                            name="password"
                            class="auth-form-control @error('password') border-danger @enderror"
                            style="margin-bottom: 0;"
                            placeholder="Masukkan password Anda"
                            required
                            autocomplete="current-password"
                        >
                        {{--
                            Tombol toggle show/hide password
                            onclick memanggil fungsi JavaScript di bawah
                        --}}
                        <button
                            type="button"
                            onclick="togglePassword('password', 'eyeIcon')"
                            style="position:absolute; right:12px; top:50%; transform:translateY(-50%);
                                   background:none; border:none; color:rgba(255,255,255,0.4);
                                   cursor:pointer; padding:0; font-size:16px;"
                        >
                            <i class="bi bi-eye" id="eyeIcon"></i>
                        </button>
                    </div>
                    @error('password')
                        <div style="color:#ff8a80; font-size:11px; margin-top:-10px; margin-bottom:10px;">
                            <i class="bi bi-exclamation-circle me-1"></i>{{ $message }}
                        </div>
                    @enderror
                </div>

                <button type="submit" class="auth-btn" id="loginBtn">
                    <i class="bi bi-box-arrow-in-right me-2"></i> Masuk ke Dashboard
                </button>
            </form>

            {{-- Link ke halaman register --}}
            <div class="auth-link">
                Belum punya akun?
                <a href="/register">Daftar Sekarang</a>
            </div>

            {{-- Teks kecil di bawah (informasi versi) --}}
            <div style="text-align:center; margin-top:24px; padding-top:20px;
                        border-top:1px solid rgba(255,255,255,0.08);">
                <small style="color:rgba(255,255,255,0.2); font-size:10px;">
                    &copy; 2026 Misbahul munir. All Rights Reserved
                </small>
            </div>

        </div>{{-- /auth-card --}}
    </div>{{-- /auth-page --}}


    <script>
        /**
         * Fungsi toggle show/hide password
         * @param {string} inputId  - ID dari input password
         * @param {string} iconId   - ID dari ikon mata
         *
         * Cara kerja:
         * - Ambil elemen input password berdasarkan ID
         * - Cek type-nya: jika "password" ubah ke "text" (terlihat), sebaliknya balik ke "password"
         * - Ganti ikon sesuai kondisi
         */
        function togglePassword(inputId, iconId) {
            const input = document.getElementById(inputId);
            const icon  = document.getElementById(iconId);

            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'bi bi-eye-slash';
            } else {
                input.type = 'password';
                icon.className = 'bi bi-eye';
            }
        }

        /**
         * Efek loading saat form disubmit
         * Mengganti teks tombol dengan spinner agar user tahu proses sedang berjalan
         */
        document.querySelector('form').addEventListener('submit', function() {
            const btn = document.getElementById('loginBtn');
            btn.innerHTML = '<span class="spinner-supply me-2"></span> Memverifikasi...';
            btn.disabled = true;
        });
    </script>

</body>
</html>