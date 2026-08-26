<?php
// Tentukan zona waktu dan hubungkan database
date_default_timezone_set("Asia/Makassar");
include __DIR__ . "/../config.php";

$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Kontrol Akses RFID</title>
    <!-- FontAwesome & Google Fonts -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<div class="app-wrapper">
    <!-- Sidebar -->
    <aside class="sidebar" id="appSidebar">
        <div class="sidebar-brand">
            <i class="fas fa-key"></i>
            <span>RFID ACCESS CONTROL</span>
        </div>
        <ul class="sidebar-menu">
            <li class="<?= $currentPage == 'index.php' ? 'active' : '' ?>">
                <a href="index.php">
                    <i class="fas fa-chart-pie"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="<?= $currentPage == 'dosen.php' ? 'active' : '' ?>">
                <a href="dosen.php">
                    <i class="fas fa-user-tie"></i>
                    <span>Data Dosen</span>
                </a>
            </li>
            <li class="<?= $currentPage == 'kartu.php' ? 'active' : '' ?>">
                <a href="kartu.php">
                    <i class="fas fa-id-card"></i>
                    <span>Kartu RFID</span>
                </a>
            </li>
            <li class="<?= $currentPage == 'ruangan.php' ? 'active' : '' ?>">
                <a href="ruangan.php">
                    <i class="fas fa-door-open"></i>
                    <span>Data Ruangan</span>
                </a>
            </li>
            <li class="<?= $currentPage == 'jadwal.php' ? 'active' : '' ?>">
                <a href="jadwal.php">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Jadwal Akses</span>
                </a>
            </li>
            <li class="<?= $currentPage == 'roles.php' ? 'active' : '' ?>">
                <a href="roles.php">
                    <i class="fas fa-user-shield"></i>
                    <span>Role Pengguna</span>
                </a>
            </li>
            <li class="<?= $currentPage == 'device.php' ? 'active' : '' ?>">
                <a href="device.php">
                    <i class="fas fa-microchip"></i>
                    <span>Device Reader</span>
                </a>
            </li>
            <li class="<?= $currentPage == 'log.php' ? 'active' : '' ?>">
                <a href="log.php">
                    <i class="fas fa-history"></i>
                    <span>Log Akses</span>
                </a>
            </li>
            <li style="margin-top: auto; padding-top: 20px; border-top: 1px solid rgba(255,255,255,0.05);">
                <a href="logout.php" style="color: #ef4444;">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout Admin</span>
                </a>
            </li>
        </ul>
    </aside>

    <!-- Main Content wrapper -->
    <div class="main-content">
        <!-- Topbar -->
        <header class="topbar">
            <div class="topbar-title">
                <?php
                switch($currentPage) {
                    case 'index.php': echo 'Dashboard Utama'; break;
                    case 'dosen.php': echo 'Manajemen Data Dosen'; break;
                    case 'kartu.php': echo 'Registrasi Kartu RFID'; break;
                    case 'ruangan.php': echo 'Manajemen Ruangan'; break;
                    case 'jadwal.php': echo 'Jadwal Akses Kelas'; break;
                    case 'roles.php': echo 'Pengaturan Role Akses'; break;
                    case 'device.php': echo 'Registrasi Device Reader'; break;
                    case 'log.php': echo 'Riwayat Log Akses'; break;
                    default: echo 'Sistem RFID';
                }
                ?>
            </div>
            <div class="topbar-status">
                <span class="status-dot pulse"></span>
                <span>RFID System Online</span>
            </div>
        </header>
        
        <!-- Content Body -->
        <main class="content-body">
