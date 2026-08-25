<?php
include "session_check.php";
include "include/header.php";

// =========================
// Hitung Data Dashboard
// =========================
$jmlDosen    = $conn->query("SELECT COUNT(*) AS total FROM users")->fetch_assoc()['total'];
$jmlKartu    = $conn->query("SELECT COUNT(*) AS total FROM kartu")->fetch_assoc()['total'];
$jmlRuangan  = $conn->query("SELECT COUNT(*) AS total FROM ruangan")->fetch_assoc()['total'];
$jmlRole     = $conn->query("SELECT COUNT(*) AS total FROM roles")->fetch_assoc()['total'];
$jmlDevice   = $conn->query("SELECT COUNT(*) AS total FROM devices")->fetch_assoc()['total'];
$jmlLog      = $conn->query("SELECT COUNT(*) AS total FROM akses_log")->fetch_assoc()['total'];

// Ambil 5 Log Akses Terakhir
$recentLogs = $conn->query("
    SELECT l.*, COALESCE(k.uid, l.scanned_uid) AS uid, u.nama, r.nama_ruangan 
    FROM akses_log l 
    LEFT JOIN kartu k ON l.id_kartu = k.id_kartu 
    LEFT JOIN users u ON l.id_user = u.id_user 
    LEFT JOIN ruangan r ON l.id_ruangan = r.id_ruangan 
    ORDER BY l.waktu_akses DESC 
    LIMIT 5
");
?>

<!-- Grid Metrik Statistik -->
<div class="grid-6">
    <div class="stat-card">
        <div class="stat-info">
            <h3>Dosen</h3>
            <h1><?= $jmlDosen ?></h1>
        </div>
        <div class="stat-icon icon-blue">
            <i class="fas fa-user-tie"></i>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-info">
            <h3>Kartu RFID</h3>
            <h1><?= $jmlKartu ?></h1>
        </div>
        <div class="stat-icon icon-indigo">
            <i class="fas fa-id-card"></i>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-info">
            <h3>Ruangan</h3>
            <h1><?= $jmlRuangan ?></h1>
        </div>
        <div class="stat-icon icon-green">
            <i class="fas fa-door-open"></i>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-info">
            <h3>Role</h3>
            <h1><?= $jmlRole ?></h1>
        </div>
        <div class="stat-icon icon-purple">
            <i class="fas fa-user-shield"></i>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-info">
            <h3>Device</h3>
            <h1><?= $jmlDevice ?></h1>
        </div>
        <div class="stat-icon icon-orange">
            <i class="fas fa-microchip"></i>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-info">
            <h3>Total Log</h3>
            <h1><?= $jmlLog ?></h1>
        </div>
        <div class="stat-icon icon-red">
            <i class="fas fa-history"></i>
        </div>
    </div>
</div>

<!-- Layout Dua Kolom -->
<div class="grid-2">
    <!-- Panel Log Terakhir -->
    <div class="panel">
        <div class="panel-header">
            <h2 class="panel-title">
                <i class="fas fa-clock" style="color: var(--primary);"></i>
                Aktivitas Akses Terakhir
            </h2>
            <a href="log.php" class="btn btn-secondary btn-sm">
                Lihat Semua
            </a>
        </div>
        <div class="table-responsive">
            <table class="modern-table">
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>UID Kartu</th>
                        <th>Nama Dosen</th>
                        <th>Ruangan</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($recentLogs && $recentLogs->num_rows > 0): ?>
                        <?php while ($log = $recentLogs->fetch_assoc()): ?>
                            <tr>
                                <td><?= date("d/m/Y H:i:s", strtotime($log['waktu_akses'])) ?></td>
                                <td>
                                    <?php if (empty($log['id_kartu'])): ?>
                                        <strong style="color: #ef4444;"><?= htmlspecialchars($log['uid'] ?? 'N/A') ?></strong>
                                    <?php else: ?>
                                        <strong><?= htmlspecialchars($log['uid'] ?? 'N/A') ?></strong>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($log['nama'] ?? 'Kartu Tidak Dikenal') ?></td>
                                <td><?= htmlspecialchars($log['nama_ruangan'] ?? 'N/A') ?></td>
                                <td>
                                    <?php if ($log['status'] == 'ALLOW'): ?>
                                        <span class="badge badge-success">ALLOW</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">DENY</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" align="center" style="color: var(--text-muted); padding: 30px 10px;">
                                <i class="fas fa-info-circle" style="font-size: 24px; display: block; margin-bottom: 10px;"></i>
                                Belum ada aktivitas akses terdeteksi.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Panel Monitor Live RFID Scanner -->
    <div>
        <div class="panel live-monitor-card">
            <div class="panel-header">
                <h2 class="panel-title">
                    <i class="fas fa-satellite-dish" style="color: #818cf8; animation: blink 1s infinite alternate;"></i>
                    Live RFID Monitor
                </h2>
            </div>
            <div class="uid-display" id="live-uid">Menunggu kartu...</div>
            <div class="time-display" id="live-time">Dekatkan kartu RFID ke scanner</div>
        </div>
        
        <!-- Panel Perangkat Tersambung Real-time -->
        <div class="panel" style="margin-top: 20px;">
            <div class="panel-header" style="display: flex; justify-content: space-between; align-items: center;">
                <h2 class="panel-title">
                    <i class="fas fa-signal" style="color: #10b981;"></i>
                    Status Device Tersambung
                </h2>
                <span class="badge badge-info" id="device-summary-badge">
                    Online: <strong id="online-count">0</strong> / <span id="total-count">0</span>
                </span>
            </div>
            <div class="table-responsive" style="max-height: 250px; overflow-y: auto;">
                <table class="modern-table" style="font-size: 13px;">
                    <thead>
                        <tr>
                            <th>Device ID / Nama</th>
                            <th>Ruangan</th>
                            <th>IP Address</th>
                            <th>Status</th>
                            <th>Terakhir Aktif</th>
                        </tr>
                    </thead>
                    <tbody id="device-status-list">
                        <tr>
                            <td colspan="5" align="center" style="color: var(--text-muted); padding: 15px;">
                                <i class="fas fa-spinner fa-spin"></i> Memuat status device...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="panel" style="margin-top: 20px;">
            <div class="panel-header">
                <h2 class="panel-title">
                    <i class="fas fa-info-circle" style="color: var(--warning);"></i>
                    Panduan Sistem
                </h2>
            </div>
            <div class="info-block">
                Sistem ini memantau pembacaan kartu RFID dan status perangkat ESP32 secara real-time. Hubungkan reader ESP32 ke API endpoint <code>/api/scan.php</code> atau <code>/api/ping.php</code> untuk pemantauan konektivitas.
            </div>
        </div>
    </div>
</div>

<style>
@keyframes blink {
    from { opacity: 0.4; }
    to { opacity: 1; }
}

.pulse-dot {
    display: inline-block;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background-color: #10b981;
    box-shadow: 0 0 0 rgba(16, 185, 129, 0.4);
    animation: pulse 1.5s infinite;
    margin-right: 6px;
    vertical-align: middle;
}
@keyframes pulse {
    0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
    70% { box-shadow: 0 0 0 8px rgba(16, 185, 129, 0); }
    100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
}

.offline-dot {
    display: inline-block;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background-color: #94a3b8;
    margin-right: 6px;
    vertical-align: middle;
}
</style>

<script>
function loadUID() {
    fetch("api/last_scan.php?" + Date.now())
    .then(res => res.json())
    .then(data => {
        const uidElem = document.getElementById("live-uid");
        if(data.uid) {
            uidElem.innerHTML = data.uid;
            if (data.registered === false) {
                uidElem.style.color = "#ef4444";
            } else {
                uidElem.style.color = "";
            }
            document.getElementById("live-time").innerHTML = "Terdeteksi pada: " + data.time;
        } else {
            uidElem.innerHTML = "Menunggu kartu...";
            uidElem.style.color = "";
            document.getElementById("live-time").innerHTML = "Dekatkan kartu RFID ke scanner";
        }
    })
    .catch(err => {
        console.error("Gagal memuat scan terakhir:", err);
    });
}

function loadDeviceStatus() {
    fetch("api/device_status.php?" + Date.now())
    .then(res => res.json())
    .then(data => {
        if (!data.success) return;
        
        document.getElementById("online-count").innerText = data.online_devices;
        document.getElementById("total-count").innerText = data.total_devices;
        
        const tbody = document.getElementById("device-status-list");
        if (data.devices.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="5" align="center" style="color: var(--text-muted); padding: 15px;">
                        Belum ada device terdaftar. Tambahkan di menu Device Reader.
                    </td>
                </tr>`;
            return;
        }
        
        let html = "";
        data.devices.forEach(dev => {
            const statusBadge = dev.is_online 
                ? `<span class="badge badge-success" style="background:#d1fae5; color:#065f46; border:1px solid #10b981;"><span class="pulse-dot"></span>ONLINE</span>`
                : `<span class="badge badge-danger" style="background:#fee2e2; color:#991b1b; border:1px solid #ef4444;"><span class="offline-dot"></span>OFFLINE</span>`;
                
            html += `
                <tr>
                    <td>
                        <strong>${escapeHtml(dev.device_id)}</strong><br>
                        <small style="color: var(--text-muted);">${escapeHtml(dev.nama_device)}</small>
                    </td>
                    <td>${escapeHtml(dev.nama_ruangan)}</td>
                    <td><code>${escapeHtml(dev.ip_address)}</code></td>
                    <td>${statusBadge}</td>
                    <td style="font-size: 12px; color: var(--text-muted); font-weight: 500;">${dev.last_seen}</td>
                </tr>`;
        });
        tbody.innerHTML = html;
    })
    .catch(err => {
        console.error("Gagal memuat status device:", err);
    });
}

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}

loadUID();
loadDeviceStatus();

setInterval(loadUID, 1000);
setInterval(loadDeviceStatus, 1000);
</script>

<?php
include "include/footer.php";
?>