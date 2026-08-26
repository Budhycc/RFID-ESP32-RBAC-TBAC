<?php
header("Content-Type: application/json");
date_default_timezone_set("Asia/Makassar");
include_once __DIR__ . "/../config.php";

// Ambil semua device beserta ruangan dan selisih detik dari last_seen
$query = "
    SELECT d.id_device, d.device_id, d.nama_device, d.status AS status_device, d.last_seen, d.ip_address, r.nama_ruangan,
           TIMESTAMPDIFF(SECOND, d.last_seen, NOW()) AS last_seen_seconds
    FROM devices d
    LEFT JOIN ruangan r ON d.id_ruangan = r.id_ruangan
    ORDER BY d.id_device ASC
";

$res = $conn->query($query);
$devices = [];
$online_count = 0;
$total_count = 0;

if ($res) {
    while ($row = $res->fetch_assoc()) {
        $total_count++;
        $sec = $row['last_seen_seconds'] !== null ? intval($row['last_seen_seconds']) : null;
        
        // Device dianggap Online jika status aktif dan last_seen <= 15 detik yang lalu
        $is_online = ($row['status_device'] === 'aktif' && $row['last_seen'] !== null && $sec !== null && $sec >= 0 && $sec <= 15);
        if ($is_online) {
            $online_count++;
        }

        // Format teks waktu relatif untuk tampilan cepat & akurat
        $last_seen_text = 'Belum Pernah';
        if ($row['last_seen']) {
            if ($sec === null || $sec < 0) {
                $last_seen_text = 'Baru saja';
            } else if ($sec < 60) {
                $last_seen_text = $sec . ' dtk lalu';
            } else if ($sec < 3600) {
                $last_seen_text = floor($sec / 60) . ' mnt lalu';
            } else {
                $last_seen_text = date("d/m/Y H:i:s", strtotime($row['last_seen']));
            }
        }

        $devices[] = [
            "id_device" => $row['id_device'],
            "device_id" => $row['device_id'],
            "nama_device" => $row['nama_device'],
            "nama_ruangan" => $row['nama_ruangan'] ?? 'Tanpa Ruangan',
            "status_konfigurasi" => $row['status_device'],
            "is_online" => $is_online,
            "seconds_ago" => $sec,
            "ip_address" => $row['ip_address'] ?? 'N/A',
            "last_seen" => $last_seen_text
        ];
    }
}

echo json_encode([
    "success" => true,
    "total_devices" => $total_count,
    "online_devices" => $online_count,
    "devices" => $devices
]);
