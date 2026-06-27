<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar — Global Supply Chain Monitor</title>
    <meta name="description" content="Daftar akun baru platform monitoring risiko rantai pasok global">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
</head>
<body>

    <div class="auth-page">
        <div class="auth-card" style="max-width: 460px;">

            {{-- Logo & Judul --}}
            <div class="auth-logo">
                <div class="logo-icon">
                    <i class="bi bi-person-plus-fill" style="color:#fff;"></i>
                </div>
                <h4>Buat Akun Baru</h4>
                <p>Daftar untuk akses platform monitoring</p>
            </div>

            {{--
                Form Register
                action="/daftar" → dikirim ke route POST /daftar
                Semua input akan divalidasi di AuthController::register()
            --}}
            <form action="/daftar" method="post">
                @csrf

                {{-- Field Nama Lengkap --}}
                <div>
                    <label for="name" class="auth-form-label">
                        <i class="bi bi-person me-1"></i> Nama Lengkap
                    </label>
                    <input
                        type="text"
                        id="name"
                        name="name"
                        class="auth-form-control"
                        value="{{ old('name') }}"
                        placeholder="Masukkan nama lengkap"
                        required
                        autocomplete="name"
                    >
                    @error('name')
                        <div style="color:#ff8a80; font-size:11px; margin-top:-10px; margin-bottom:10px;">
                            <i class="bi bi-exclamation-circle me-1"></i>{{ $message }}
                        </div>
                    @enderror
                </div>

                {{-- Field Email --}}
                <div>
                    <label for="email" class="auth-form-label">
                        <i class="bi bi-envelope me-1"></i> Email
                    </label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        class="auth-form-control"
                        value="{{ old('email') }}"
                        placeholder="Masukkan email"
                        required
                        autocomplete="email"
                    >
                    @error('email')
                        <div style="color:#ff8a80; font-size:11px; margin-top:-10px; margin-bottom:10px;">
                            <i class="bi bi-exclamation-circle me-1"></i>{{ $message }}
                        </div>
                    @enderror
                </div>

                {{-- Field Password --}}
                <div>
                    <label for="password" class="auth-form-label">
                        <i class="bi bi-lock me-1"></i> Password
                    </label>
                    <div style="position:relative; margin-bottom: 16px;">
                        <input
                            type="password"
                            id="password"
                            name="password"
                            class="auth-form-control"
                            style="margin-bottom: 0;"
                            placeholder="Minimal 6 karakter"
                            required
                        >
                        <button
                            type="button"
                            onclick="togglePassword('password', 'eye1')"
                            style="position:absolute; right:12px; top:50%; transform:translateY(-50%);
                                   background:none; border:none; color:rgba(255,255,255,0.4);
                                   cursor:pointer; padding:0; font-size:16px;"
                        >
                            <i class="bi bi-eye" id="eye1"></i>
                        </button>
                    </div>
                    @error('password')
                        <div style="color:#ff8a80; font-size:11px; margin-top:-10px; margin-bottom:10px;">
                            <i class="bi bi-exclamation-circle me-1"></i>{{ $message }}
                        </div>
                    @enderror
                </div>

                {{-- Field Konfirmasi Password --}}
                <div>
                    <label for="confirm_password" class="auth-form-label">
                        <i class="bi bi-lock-fill me-1"></i> Konfirmasi Password
                    </label>
                    <div style="position:relative; margin-bottom: 16px;">
                        <input
                            type="password"
                            id="confirm_password"
                            name="confirm_password"
                            class="auth-form-control"
                            style="margin-bottom: 0;"
                            placeholder="Ulangi password Anda"
                            required
                        >
                        <button
                            type="button"
                            onclick="togglePassword('confirm_password', 'eye2')"
                            style="position:absolute; right:12px; top:50%; transform:translateY(-50%);
                                   background:none; border:none; color:rgba(255,255,255,0.4);
                                   cursor:pointer; padding:0; font-size:16px;"
                        >
                            <i class="bi bi-eye" id="eye2"></i>
                        </button>
                    </div>
                    @error('confirm_password')
                        <div style="color:#ff8a80; font-size:11px; margin-top:-10px; margin-bottom:10px;">
                            <i class="bi bi-exclamation-circle me-1"></i>{{ $message }}
                        </div>
                    @enderror
                </div>

                {{--
                    Indikator kekuatan password (visual real-time)
                    Diupdate oleh JavaScript saat user mengetik
                --}}
                <div style="margin-top:-8px; margin-bottom:16px;">
                    <div style="display:flex; gap:4px; margin-bottom:4px;">
                        <div id="str1" style="height:3px; flex:1; background:#e0eaf6; border-radius:2px; transition:background 0.3s;"></div>
                        <div id="str2" style="height:3px; flex:1; background:#e0eaf6; border-radius:2px; transition:background 0.3s;"></div>
                        <div id="str3" style="height:3px; flex:1; background:#e0eaf6; border-radius:2px; transition:background 0.3s;"></div>
                        <div id="str4" style="height:3px; flex:1; background:#e0eaf6; border-radius:2px; transition:background 0.3s;"></div>
                    </div>
                    <small id="strLabel" style="color:rgba(255,255,255,0.3); font-size:10px;"></small>
                </div>

                <button type="submit" class="auth-btn" id="registerBtn">
                    <i class="bi bi-person-check me-2"></i> Buat Akun
                </button>
            </form>

            <div class="auth-link">
                Sudah punya akun?
                <a href="/login">Login Sekarang</a>
            </div>

            <div style="text-align:center; margin-top:20px; padding-top:16px;
                        border-top:1px solid rgba(255,255,255,0.08);">
                <small style="color:rgba(255,255,255,0.2); font-size:10px;">
                    &copy; 2026 Misbahul munir. All Rights Reserved
                </small>
            </div>

        </div>
    </div>


    <script>
        /**
         * Toggle Show/Hide Password (sama seperti login.blade.php)
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
         * Indikator Kekuatan Password (Password Strength Meter)
         *
         * Cara kerja:
         * - Mendengarkan event 'input' pada field password
         * - Menghitung skor berdasarkan:
         *   · Panjang ≥ 6    → +1 poin
         *   · Ada huruf BESAR → +1 poin
         *   · Ada angka       → +1 poin
         *   · Ada karakter spesial → +1 poin
         * - Mengubah warna bar sesuai skor (merah → orange → kuning → hijau)
         */
        document.getElementById('password').addEventListener('input', function() {
            const val = this.value;
            let score = 0;
            if (val.length >= 6)              score++;  // Panjang cukup
            if (/[A-Z]/.test(val))            score++;  // Ada huruf kapital
            if (/[0-9]/.test(val))            score++;  // Ada angka
            if (/[^A-Za-z0-9]/.test(val))    score++;  // Ada karakter khusus (!@#$)

            // Warna per level: 1=merah, 2=orange, 3=kuning, 4=hijau
            const colors = ['', '#f44336', '#ff9100', '#ffc107', '#00c853'];
            const labels = ['', 'Lemah', 'Sedang', 'Kuat', 'Sangat Kuat'];

            for (let i = 1; i <= 4; i++) {
                const bar = document.getElementById('str' + i);
                bar.style.background = i <= score ? colors[score] : '#2a3e56';
            }

            document.getElementById('strLabel').textContent = val.length > 0 ? labels[score] : '';
            document.getElementById('strLabel').style.color =
                score > 0 ? colors[score] : 'rgba(255,255,255,0.3)';
        });

        /**
         * Efek loading pada tombol submit
         */
        document.querySelector('form').addEventListener('submit', function() {
            const btn = document.getElementById('registerBtn');
            btn.innerHTML = '<span class="spinner-supply me-2"></span> Membuat Akun...';
            btn.disabled = true;
        });
    </script>

</body>
</html>