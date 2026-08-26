<?php
header("Content-Type: application/json");
date_default_timezone_set("Asia/Makassar");
include_once __DIR__ . "/../config.php";

$device_id = strtoupper(trim($_GET['device_id'] ?? $_POST['device_id'] ?? ''));

// Jika dikirim via JSON raw body
if ($device_id === '') {
    $rawInput = json_decode(file_get_contents("php://input"), true);
    if (isset($rawInput['device_id'])) {
        $device_id = strtoupper(trim($rawInput['device_id']));
    }
}

if ($device_id === '') {
    echo json_encode([
        "success" => false,
        "message" => "Device ID kosong"
    ]);
    exit;
}

$ip = $_SERVER['REMOTE_ADDR'] ?? '';

// Cek apakah device_id sudah terdaftar
$check = $conn->prepare("SELECT id_device FROM devices WHERE device_id = ?");
$check->bind_param("s", $device_id);
$check->execute();
$resCheck = $check->get_result();

if ($resCheck && $resCheck->num_rows > 0) {
    // Device sudah ada -> Update last_seen dan ip_address
    $stmt = $conn->prepare("UPDATE devices SET last_seen = NOW(), ip_address = ? WHERE device_id = ?");
    $stmt->bind_param("ss", $ip, $device_id);
    $stmt->execute();
} else {
    // Device belum ada di database -> Otomatis daftarkan agar langsung terdeteksi ONLINE di dashboard!
    $ruang = $conn->query("SELECT id_ruangan FROM ruangan ORDER BY id_ruangan ASC LIMIT 1")->fetch_assoc();
    $id_ruangan = $ruang['id_ruangan'] ?? 1;
    $nama_device = "ESP32 Reader (" . $device_id . ")";
    
    $ins = $conn->prepare("INSERT INTO devices (device_id, nama_device, id_ruangan, status, last_seen, ip_address) VALUES (?, ?, ?, 'aktif', NOW(), ?)");
    $ins->bind_param("ssis", $device_id, $nama_device, $id_ruangan, $ip);
    $ins->execute();
}

echo json_encode([
    "success" => true,
    "message" => "Heartbeat received",
    "device_id" => $device_id,
    "time" => date("Y-m-d H:i:s")
]);
