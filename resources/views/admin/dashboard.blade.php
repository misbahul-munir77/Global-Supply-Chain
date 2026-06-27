<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin — Global Supply Chain Monitor</title>
    
    {{-- Google Fonts: Inter --}}
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f0f4f8;
            color: #1a2332;
        }
        
        .admin-header { 
            background: var(--bg-dark); 
            color: #fff; 
            padding: 12px 24px;
            display: flex; 
            justify-content: space-between; 
            align-items: center;
            border-bottom: 1px solid rgba(255,255,255,0.08); 
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 56px;
            z-index: 1000;
        }
        
        .admin-body { 
            padding: 24px; 
            background: #f0f4f8; 
            min-height: 100vh;
            margin-top: 56px;
        }
        
        /* Stat boxes premium style */
        .stat-box-premium {
            background: #ffffff;
            border: 1px solid #e8edf4;
            border-radius: 12px;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.04);
            transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
        }
        
        .stat-box-premium:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(26, 115, 232, 0.1);
            border-color: rgba(26, 115, 232, 0.3);
        }
        
        .stat-box-premium .num {
            font-size: 28px;
            font-weight: 800;
            color: #1a2332;
            line-height: 1.2;
            margin-top: 2px;
        }
        
        .stat-box-premium .lbl {
            font-size: 11px;
            text-transform: uppercase;
            color: #6b7c93;
            font-weight: 700;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }
        
        .stat-box-icon {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }
        
        .stat-box-icon.blue { background: #e3f0ff; color: #1a73e8; }
        .stat-box-icon.green { background: #e8f5e9; color: #2e7d32; }
        .stat-box-icon.orange { background: #fff3e0; color: #e65100; }
        .stat-box-icon.purple { background: #f3e5f5; color: #8e24aa; }

        /* Search input bar style */
        .search-box {
            position: relative;
            max-width: 280px;
            width: 100%;
        }
        .search-box .search-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #6b7c93;
            font-size: 13px;
        }
        .search-box .search-input {
            padding-left: 34px;
            border-radius: 8px;
            border: 1px solid #dde4ed;
            font-size: 13px;
            background-color: #f7faff;
            height: 36px;
            transition: all 0.2s;
        }
        .search-box .search-input:focus {
            background-color: #fff;
            border-color: #1a73e8;
            box-shadow: 0 0 0 3px rgba(26, 115, 232, 0.1);
            outline: none;
        }

        /* Table custom styling */
        .table-supply thead th { 
            background: #f7faff; 
            font-size: 11px; 
            font-weight: 700;
            color: #6b7c93; 
            text-transform: uppercase; 
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e0eaf6; 
            padding: 12px 16px;
        }
        
        .table-supply tbody td { 
            font-size: 13px; 
            vertical-align: middle; 
            padding: 14px 16px;
            color: #1a2332;
            border-bottom: 1px solid #f0f4f8;
        }
        
        .table-supply tbody tr {
            transition: background 0.15s ease;
        }
        
        .table-supply tbody tr:hover {
            background-color: #f8faff;
        }
        
        .role-badge-admin { 
            background: #ffebee; 
            color: #c62828; 
            padding: 4px 12px; 
            border-radius: 20px; 
            font-size: 11px; 
            font-weight: 700; 
            border: 1px solid rgba(198, 40, 40, 0.15);
        }
        
        .role-badge-user  { 
            background: #e8f5e9; 
            color: #2e7d32; 
            padding: 4px 12px; 
            border-radius: 20px; 
            font-size: 11px; 
            font-weight: 700; 
            border: 1px solid rgba(46, 125, 50, 0.15);
        }

        .btn-view-platform {
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 8px;
            color: #fff;
            padding: 6px 14px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .btn-view-platform:hover {
            background: rgba(255,255,255,0.16);
            color: #00c6ff;
            border-color: rgba(0,198,255,0.3);
        }
    </style>
</head>
<body>

    {{-- Navbar Admin --}}
    <div class="admin-header">
        <div style="display:flex; align-items:center; gap:10px;">
            <div style="width:32px;height:32px;background:linear-gradient(135deg,#1a73e8,#00c6ff);border-radius:8px;
                        display:flex;align-items:center;justify-content:center;box-shadow: 0 4px 10px rgba(26,115,232,0.3);">
                <i class="bi bi-box-seam-fill" style="color:#fff;"></i>
            </div>
            <span style="font-weight:700; font-size:15px; letter-spacing: 0.2px;">Admin Panel — Supply Chain Ops</span>
        </div>
        <div style="display:flex; align-items:center; gap:14px;">
            <span style="color:rgba(255,255,255,0.6); font-size:13px; display: none; display: inline-block;">
                Admin: <strong style="color:#fff;">{{ Auth::user()->name }}</strong>
            </span>
            <a href="/user" class="btn-view-platform">
                <i class="bi bi-globe2"></i>Lihat Platform Monitoring
            </a>
            <a href="/logout" class="btn-logout" style="height:34px; padding: 6px 16px;" onclick="return confirm('Yakin logout?')">
                <i class="bi bi-box-arrow-right me-1"></i>Logout
            </a>
        </div>
    </div>

    <div class="admin-body">

        {{-- Judul --}}
        <div class="mb-4">
            <h4 style="font-weight:800; color:#1a2332; margin:0;">Dashboard Admin</h4>
            <small style="color:#6b7c93;">Manajemen pengguna & pemantauan data API terintegrasi</small>
        </div>

        {{-- Stat boxes --}}
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="stat-box-premium">
                    <div>
                        <div class="lbl">Total Pengguna</div>
                        <div class="num">{{ \App\Models\User::count() }}</div>
                    </div>
                    <div class="stat-box-icon blue">
                        <i class="bi bi-people-fill"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-box-premium">
                    <div>
                        <div class="lbl">User Aktif</div>
                        <div class="num">{{ \App\Models\User::where('role','user')->count() }}</div>
                    </div>
                    <div class="stat-box-icon green">
                        <i class="bi bi-person-check-fill"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-box-premium">
                    <div>
                        <div class="lbl">Administrator</div>
                        <div class="num">{{ \App\Models\User::where('role','admin')->count() }}</div>
                    </div>
                    <div class="stat-box-icon orange">
                        <i class="bi bi-shield-fill"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-box-premium">
                    <div>
                        <div class="lbl">API Terintegrasi</div>
                        <div class="num">7</div>
                    </div>
                    <div class="stat-box-icon purple">
                        <i class="bi bi-globe2"></i>
                    </div>
                </div>
            </div>
        </div>

        {{-- Tabel User --}}
        <div style="background:#fff; border-radius:12px; box-shadow:0 2px 12px rgba(0,0,0,0.04); border:1px solid #e8edf4; overflow:hidden;">
            <div style="padding:16px 20px; border-bottom:1px solid #f0f4f8; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
                <h6 style="font-weight:700; margin:0; color:#1a2332; display:flex; align-items:center; gap:8px;">
                    <i class="bi bi-people-fill" style="color:#1a73e8; font-size:18px;"></i>Daftar Pengguna Sistem
                </h6>
                <div class="search-box">
                    <i class="bi bi-search search-icon"></i>
                    <input type="text" id="userSearchInput" class="form-control form-control-sm search-input" placeholder="Cari nama atau email...">
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-supply mb-0">
                    <thead>
                        <tr>
                            <th style="padding-left: 20px; width: 80px;">No</th>
                            <th>Nama Lengkap</th>
                            <th>Email</th>
                            <th>Hak Akses / Role</th>
                            <th>Tanggal Bergabung</th>
                        </tr>
                    </thead>
                    <tbody id="userTableBody">
                        @foreach(\App\Models\User::orderBy('created_at','desc')->get() as $i => $u)
                        <tr data-name="{{ $u->name }}" data-email="{{ $u->email }}">
                            <td style="padding-left: 20px; color:#6b7c93; font-weight: 500;">{{ $i + 1 }}</td>
                            <td>
                                <div style="display:flex; align-items:center; gap:10px;">
                                    <div style="width:32px;height:32px;border-radius:50%;
                                                background:linear-gradient(135deg,#1a73e8,#00c6ff);
                                                display:flex;align-items:center;justify-content:center;
                                                color:#fff;font-weight:700;font-size:12px;flex-shrink:0;
                                                box-shadow: 0 2px 6px rgba(26,115,232,0.2);">
                                        {{ strtoupper(substr($u->name, 0, 1)) }}
                                    </div>
                                    <span style="font-weight:600; color:#1a2332;">{{ $u->name }}</span>
                                </div>
                            </td>
                            <td style="color:#4a5568; font-weight: 500;">{{ $u->email }}</td>
                            <td>
                                @if($u->role === 'admin')
                                    <span class="role-badge-admin"><i class="bi bi-shield me-1"></i>Admin</span>
                                @else
                                    <span class="role-badge-user"><i class="bi bi-person me-1"></i>User</span>
                                @endif
                            </td>
                            <td style="color:#6b7c93; font-size:12px; font-weight: 500;">
                                {{ $u->created_at ? $u->created_at->format('d M Y') : 'N/A' }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Fitur Pencarian Real-Time Dinamis (Sisi Klien)
        document.getElementById('userSearchInput').addEventListener('input', function() {
            const query = this.value.toLowerCase().trim();
            const rows  = document.querySelectorAll('#userTableBody tr');

            rows.forEach(row => {
                const name  = row.getAttribute('data-name').toLowerCase();
                const email = row.getAttribute('data-email').toLowerCase();
                if (name.includes(query) || email.includes(query)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>