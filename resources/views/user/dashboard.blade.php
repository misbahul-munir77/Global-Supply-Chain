<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Global Supply Chain Monitor</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        /* Warna Dark Navy Premium untuk Navbar & Sidebar */
        .bg-supply-dark {
            background-color: #03142c !important; 
        }
        
        /* Mengatur agar tinggi layout pas dengan layar browser */
        .wrapper-main {
            min-height: calc(100vh - 56px); /* 100% tinggi layar dikurangi tinggi navbar */
        }

        /* Styling menu sidebar saat di-hover/lewatkan kursor */
        .sidebar-menu .nav-link:hover {
            background-color: rgba(255, 255, 255, 0.1);
            color: #ffffff !important;
            border-radius: 6px;
        }
    </style>
</head>
<body class="bg-light">

    <nav class="navbar navbar-dark bg-supply-dark px-3 border-bottom border-secondary border-opacity-25">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center fw-bold fs-6" href="#">
                <i class="bi bi-box-seam-fill me-2 text-primary"></i> Global Supply Chain Monitor
            </a>
            
            <div class="d-flex align-items-center gap-3">
                <span class="text-white-50 small">Halo, {{ Auth::user()->name }}</span>
                <a href="/logout" class="btn btn-primary btn-sm px-3 fw-semibold">Logout</a>
            </div>
        </div>
    </nav>

    <div class="d-flex wrapper-main">
        
        <div class="bg-supply-dark text-white p-3 sidebar-menu" style="width: 240px; min-width: 240px;">
            
            <div class="mb-4 p-2 rounded bg-dark bg-opacity-25">
                <h6 class="m-0 fw-bold small text-white">Supply Chain Ops</h6>
                <small class="text-white-50" style="font-size: 11px;">Risk Monitoring v2.1</small>
            </div>
            
            <ul class="nav flex-column gap-1">
                <li class="nav-item">
                    <a class="nav-link text-white active btn btn-primary text-start d-flex align-items-center gap-2 py-2" href="#">
                        <i class="bi bi-globe me-2"></i>Peta Global
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-white-50 text-start d-flex align-items-center gap-2 py-2" href="#">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>Analisis Risiko
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-white-50 text-start d-flex align-items-center gap-2 py-2" href="#">
                        <i class="bi bi-anchor me-2"></i>Data Pelabuhan
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-white-50 text-start d-flex align-items-center gap-2 py-2" href="#">
                        <i class="bi bi-bar-chart-line-fill me-2"></i>Laporan Ekonomi
                    </a>
                </li>
            </ul>
        </div>
        
        <div class="isi-content flex-grow-1 p-4">
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="fw-bold m-0 text-dark">Dashboard Monitoring Real-Time</h4>
                    <small class="text-muted">Update terakhir: Real-time dari API penyedia data</small>
                </div>
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>