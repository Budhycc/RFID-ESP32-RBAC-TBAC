<?php
header("Content-Type: application/json");
date_default_timezone_set("Asia/Makassar");

// =======================
// Baca JSON dari ESP32
// =======================
$data = json_decode(file_get_contents("php://input"), true);

if (!$data) {
    echo json_encode([
        "success" => false,
        "access" => "DENY",
        "message" => "Data JSON kosong"
    ]);
    exit;
}
// Menyimpan data UID dan Device ID yang diterima ke variabel
$uid = strtoupper(trim($data['uid'] ?? ''));
$device_id = strtoupper(trim($data['device_id'] ?? ''));

if ($uid == "") {
    echo json_encode([
        "success" => false,
        "access" => "DENY",
        "message" => "UID tidak ditemukan"
    ]);
    exit;
}

// =======================
// Simpan UID terakhir (untuk dashboard / form register)
// =======================
$lastScan = [
    "uid"  => $uid,
    "time" => date("Y-m-d H:i:s")
];
$file = __DIR__ . "/last_uid.json";
@file_put_contents($file, json_encode($lastScan, JSON_PRETTY_PRINT), LOCK_EX);

// =======================
// Load Database Config
// =======================
include_once __DIR__ . "/../config.php";

// 1. Dapatkan Ruangan tempat Device terpasang
$id_device = null;
$id_ruangan = null;
$nama_ruangan = '';
$nama_device = '';

if ($device_id != "") {
    $q_dev = $conn->prepare("
        SELECT d.id_device, d.id_ruangan, d.nama_device, r.nama_ruangan 
        FROM devices d 
        LEFT JOIN ruangan r ON d.id_ruangan = r.id_ruangan 
        WHERE d.device_id = ? AND d.status = 'aktif'
    ");
    $q_dev->bind_param("s", $device_id);
    $q_dev->execute();
    $res_dev = $q_dev->get_result()->fetch_assoc();
    if ($res_dev) {
        $id_device = $res_dev['id_device'];
        $id_ruangan = $res_dev['id_ruangan'];
        $nama_ruangan = $res_dev['nama_ruangan'];
        $nama_device = $res_dev['nama_device'];

        // Update last_seen dan IP address device
        $ip_client = $_SERVER['REMOTE_ADDR'] ?? '';
        $u_stmt = $conn->prepare("UPDATE devices SET last_seen = NOW(), ip_address = ? WHERE id_device = ?");
        $u_stmt->bind_param("si", $ip_client, $id_device);
        $u_stmt->execute();
    }
}

// Fallback jika device_id kosong/tidak ditemukan, ambil device pertama yang aktif untuk memudahkan testing
if (!$id_device) {
    $res_dev = $conn->query("
        SELECT d.id_device, d.id_ruangan, d.nama_device, r.nama_ruangan 
        FROM devices d 
        LEFT JOIN ruangan r ON d.id_ruangan = r.id_ruangan 
        WHERE d.status = 'aktif' 
        LIMIT 1
    ")->fetch_assoc();
    if ($res_dev) {
        $id_device = $res_dev['id_device'];
        $id_ruangan = $res_dev['id_ruangan'];
        $nama_ruangan = $res_dev['nama_ruangan'];
        $nama_device = $res_dev['nama_device'];
    }
}

// 2. Cari UID di tabel Kartu RFID (Join ke tabel users dan roles untuk mendapatkan nama role/akses)
$q_card = $conn->prepare("
    SELECT k.id_kartu, k.id_user, k.status AS status_kartu, u.nama, u.nidn, u.status AS status_user, r.id_role, r.nama_role, r.jam_mulai, r.jam_akhir
    FROM kartu k 
    LEFT JOIN users u ON k.id_user = u.id_user 
    LEFT JOIN roles r ON u.id_role = r.id_role
    WHERE k.uid = ?
");
$q_card->bind_param("s", $uid);
$q_card->execute();
$card = $q_card->get_result()->fetch_assoc();

// Jika kartu tidak terdaftar
if (!$card) {
    $lastScan = [
        "uid"        => $uid,
        "time"       => date("Y-m-d H:i:s"),
        "registered" => false
    ];
    $file = __DIR__ . "/last_uid.json";
    @file_put_contents($file, json_encode($lastScan, JSON_PRETTY_PRINT), LOCK_EX);

    $status_log = 'DENY';
    $keterangan = 'Kartu belum terdaftar';
    
    $stmt_log = $conn->prepare("
        INSERT INTO akses_log (id_kartu, scanned_uid, id_user, id_device, id_ruangan, waktu_akses, status, keterangan) 
        VALUES (NULL, ?, NULL, ?, ?, NOW(), ?, ?)
    ");
    $stmt_log->bind_param("siiss", $uid, $id_device, $id_ruangan, $status_log, $keterangan);
    $stmt_log->execute();
    
    echo json_encode([
        "success" => false,
        "access" => "DENY",
        "message" => "Belum Terdaftar",
        "uid" => $uid
    ]);
    exit;
}

$lastScan = [
    "uid"        => $uid,
    "time"       => date("Y-m-d H:i:s"),
    "registered" => true
];
$file = __DIR__ . "/last_uid.json";
@file_put_contents($file, json_encode($lastScan, JSON_PRETTY_PRINT), LOCK_EX);

$id_kartu = $card['id_kartu'];
$id_user = $card['id_user'];
$nama_user = $card['nama'];
$nidn_user = isset($card['nidn']) ? $card['nidn'] : '';

// Jika kartu dinonaktifkan / diblokir
if ($card['status_kartu'] != 'aktif') {
    $status_log = 'DENY';
    $keterangan = 'Kartu diblokir / nonaktif';
    
    $stmt_log = $conn->prepare("
        INSERT INTO akses_log (id_kartu, scanned_uid, id_user, id_device, id_ruangan, waktu_akses, status, keterangan) 
        VALUES (?, ?, ?, ?, ?, NOW(), ?, ?)
    ");
    $stmt_log->bind_param("isiiiss", $id_kartu, $uid, $id_user, $id_device, $id_ruangan, $status_log, $keterangan);
    $stmt_log->execute();
    
    echo json_encode([
        "success" => false,
        "access" => "DENY",
        "message" => "Kartu Diblokir",
        "uid" => $uid,
        "name" => $nama_user
    ]);
    exit;
}

// Jika dosen/user dinonaktifkan
if ($card['status_user'] != 'aktif') {
    $status_log = 'DENY';
    $keterangan = 'User pemilik kartu nonaktif';
    
    $stmt_log = $conn->prepare("
        INSERT INTO akses_log (id_kartu, scanned_uid, id_user, id_device, id_ruangan, waktu_akses, status, keterangan) 
        VALUES (?, ?, ?, ?, ?, NOW(), ?, ?)
    ");
    $stmt_log->bind_param("isiiiss", $id_kartu, $uid, $id_user, $id_device, $id_ruangan, $status_log, $keterangan);
    $stmt_log->execute();
    
    echo json_encode([
        "success" => false,
        "access" => "DENY",
        "message" => "Akun Nonaktif",
        "uid" => $uid,
        "name" => $nama_user
    ]);
    exit;
}

// =========================================================================
//  Validasi

$nama_role = isset($card['nama_role']) ? $card['nama_role'] : '';
$jam_mulai_role = isset($card['jam_mulai']) ? $card['jam_mulai'] : '06:00:00';
$jam_akhir_role = isset($card['jam_akhir']) ? $card['jam_akhir'] : '18:00:00';
$id_role = isset($card['id_role']) ? $card['id_role'] : 0;

$user_permissions = [];
if ($id_role > 0) {
    $q_perm = $conn->prepare("
        SELECT p.nama_permission 
        FROM role_permissions rp 
        JOIN permissions p ON rp.id_permission = p.id_permission 
        WHERE rp.id_role = ?
    ");
    $q_perm->bind_param("i", $id_role);
    $q_perm->execute();
    $res_perm = $q_perm->get_result();
    while($row = $res_perm->fetch_assoc()) {
        $user_permissions[] = $row['nama_permission'];
    }
}

if (in_array('bypass_schedule', $user_permissions)) {
    $status_log = 'ALLOW';
    $keterangan = 'Akses pintu terbuka: ' . $nama_role . ' - Tanpa Jadwal';
    
    // Simpan log akses ke database
    $stmt_log = $conn->prepare("
        INSERT INTO akses_log (id_kartu, scanned_uid, id_user, id_device, id_ruangan, waktu_akses, status, keterangan) 
        VALUES (?, ?, ?, ?, ?, NOW(), ?, ?)
    ");
    $stmt_log->bind_param("isiiiss", $id_kartu, $uid, $id_user, $id_device, $id_ruangan, $status_log, $keterangan);
    $stmt_log->execute();
    
    // Kirim respons sukses ke alat / ESP32
    echo json_encode([
        "success" => true,
        "access" => "ALLOW",
        "message" => "Akses diterima. Selamat bertugas.",
        "uid" => $uid,
        "name" => $nama_user,
        "nidn" => $nidn_user,
        "role" => "admin",
        "room" => $nama_ruangan,
        "subject" => "Akses " . $nama_role
    ]);
    exit;
} else if (in_array('access_operational', $user_permissions)) {
    $jam_sekarang = date('H:i:s');
    $hari_ini = date('N');

    // 1. Cek Jam Operasional
    if ($jam_sekarang < $jam_mulai_role || $jam_sekarang > $jam_akhir_role) {
        $status_log = 'DENY';
        $keterangan = 'Akses ditolak: Diluar jam operasional (' . substr($jam_mulai_role,0,5) . '-' . substr($jam_akhir_role,0,5) . ')';
        
        $stmt_log = $conn->prepare("INSERT INTO akses_log (id_kartu, scanned_uid, id_user, id_device, id_ruangan, waktu_akses, status, keterangan) VALUES (?, ?, ?, ?, ?, NOW(), ?, ?)");
        $stmt_log->bind_param("isiiiss", $id_kartu, $uid, $id_user, $id_device, $id_ruangan, $status_log, $keterangan);
        $stmt_log->execute();
        
        echo json_encode([
            "success" => false,
            "access" => "DENY",
            "message" => "Dluar Jam Kerja",
            "uid" => $uid,
            "name" => $nama_user,
            "room" => $nama_ruangan
        ]);
        exit;
    }

    // 2. Cek Jadwal Aktif Saat Ini di Ruangan
    $q_active = $conn->prepare("
        SELECT id_user, mata_kuliah, jam_masuk, jam_keluar 
        FROM jadwal 
        WHERE id_ruangan = ? AND hari = ? AND ? BETWEEN jam_masuk AND jam_keluar
        LIMIT 1
    ");
    $q_active->bind_param("iis", $id_ruangan, $hari_ini, $jam_sekarang);
    $q_active->execute();
    $active_class = $q_active->get_result()->fetch_assoc();

    if ($active_class) {
        $id_dosen_kelas = $active_class['id_user'];
        $jam_masuk_kelas = $active_class['jam_masuk'];
        
        // 3. Cek Kehadiran Dosen
        $q_kehadiran = $conn->prepare("
            SELECT id_log FROM akses_log 
            WHERE id_ruangan = ? AND id_user = ? AND DATE(waktu_akses) = CURDATE() 
            AND TIME(waktu_akses) >= ? AND TIME(waktu_akses) <= ? AND status = 'ALLOW'
            LIMIT 1
        ");
        $q_kehadiran->bind_param("iiss", $id_ruangan, $id_dosen_kelas, $jam_masuk_kelas, $jam_sekarang);
        $q_kehadiran->execute();
        $is_dosen_hadir = $q_kehadiran->get_result()->fetch_assoc();

        if ($is_dosen_hadir) {
            $status_log = 'DENY';
            $keterangan = 'Akses ditolak: Kelas ' . $active_class['mata_kuliah'] . ' sedang berlangsung (Dosen Hadir)';
            
            $stmt_log = $conn->prepare("INSERT INTO akses_log (id_kartu, scanned_uid, id_user, id_device, id_ruangan, waktu_akses, status, keterangan) VALUES (?, ?, ?, ?, ?, NOW(), ?, ?)");
            $stmt_log->bind_param("isiiiss", $id_kartu, $uid, $id_user, $id_device, $id_ruangan, $status_log, $keterangan);
            $stmt_log->execute();
            
            echo json_encode([
                "success" => false,
                "access" => "DENY",
                "message" => "Kelas Berjalan",
                "uid" => $uid,
                "name" => $nama_user,
                "room" => $nama_ruangan
            ]);
            exit;
        }
    }

    // 4. Akses Diterima untuk Staf (Ruangan Kosong)
    $status_log = 'ALLOW';
    $keterangan = 'Akses pintu terbuka: Staf (' . $nama_user . ') - Maintenance/Kebersihan';
    
    $stmt_log = $conn->prepare("INSERT INTO akses_log (id_kartu, scanned_uid, id_user, id_device, id_ruangan, waktu_akses, status, keterangan) VALUES (?, ?, ?, ?, ?, NOW(), ?, ?)");
    $stmt_log->bind_param("isiiiss", $id_kartu, $uid, $id_user, $id_device, $id_ruangan, $status_log, $keterangan);
    $stmt_log->execute();
    
    echo json_encode([
        "success" => true,
        "access" => "ALLOW",
        "message" => "Akses diterima. Selamat bertugas.",
        "uid" => $uid,
        "name" => $nama_user,
        "role" => "staf",
        "room" => $nama_ruangan,
        "subject" => "Tugas Staf/Teknisi"
    ]);
    exit;
} else if (in_array('access_scheduled', $user_permissions)) {
    // 3. Cek Jadwal Akses Ruangan hari ini (Hanya untuk non-admin / Dosen)
    $hari_ini = date('N'); // 1 (Senin) - 7 (Minggu)
    $jam_sekarang = date('H:i:s');

$q_sched = $conn->prepare("
    SELECT * 
    FROM jadwal 
    WHERE id_user = ? AND id_ruangan = ? AND hari = ?
");
$q_sched->bind_param("iii", $id_user, $id_ruangan, $hari_ini);
$q_sched->execute();
$res_sched = $q_sched->get_result();

// Jika dosen tidak memiliki jadwal akses di ruangan tersebut hari ini
if ($res_sched->num_rows == 0) {
    $status_log = 'DENY';
    $keterangan = 'Tidak memiliki jadwal hari ini';
    
    $stmt_log = $conn->prepare("
        INSERT INTO akses_log (id_kartu, scanned_uid, id_user, id_device, id_ruangan, waktu_akses, status, keterangan) 
        VALUES (?, ?, ?, ?, ?, NOW(), ?, ?)
    ");
    $stmt_log->bind_param("isiiiss", $id_kartu, $uid, $id_user, $id_device, $id_ruangan, $status_log, $keterangan);
    $stmt_log->execute();
    
    echo json_encode([
        "success" => false,
        "access" => "DENY",
        "message" => "Tdk Ada Jadwal",
        "uid" => $uid,
        "name" => $nama_user,
        "room" => $nama_ruangan
    ]);
    exit;
}

// Jika ada jadwal hari ini, cek rentang waktunya (jam_masuk s/d jam_keluar)
$matched_sched = null;
while ($sched = $res_sched->fetch_assoc()) {
    if ($jam_sekarang >= $sched['jam_masuk'] && $jam_sekarang <= $sched['jam_keluar']) {
        $matched_sched = $sched;
        break;
    }
}

// Jika di luar jam jadwalnya
if (!$matched_sched) {
    $status_log = 'DENY';
    $keterangan = 'Akses diluar jam jadwal kuliah';
    
    $stmt_log = $conn->prepare("
        INSERT INTO akses_log (id_kartu, scanned_uid, id_user, id_device, id_ruangan, waktu_akses, status, keterangan) 
        VALUES (?, ?, ?, ?, ?, NOW(), ?, ?)
    ");
    $stmt_log->bind_param("isiiiss", $id_kartu, $uid, $id_user, $id_device, $id_ruangan, $status_log, $keterangan);
    $stmt_log->execute();
    
    echo json_encode([
        "success" => false,
        "access" => "DENY",
        "message" => "Diluar Jadwal",
        "uid" => $uid,
        "name" => $nama_user,
        "room" => $nama_ruangan
    ]);
    exit;
}

// 4. Akses Diterima (ALLOW)
$status_log = 'ALLOW';
$keterangan = 'Akses pintu terbuka: Kuliah ' . $matched_sched['mata_kuliah'];

$stmt_log = $conn->prepare("
    INSERT INTO akses_log (id_kartu, scanned_uid, id_user, id_device, id_ruangan, waktu_akses, status, keterangan) 
    VALUES (?, ?, ?, ?, ?, NOW(), ?, ?)
");
$stmt_log->bind_param("isiiiss", $id_kartu, $uid, $id_user, $id_device, $id_ruangan, $status_log, $keterangan);
$stmt_log->execute();

echo json_encode([
    "success" => true,
    "access" => "ALLOW",
    "message" => "Akses diterima. Pintu berhasil terbuka.",
    "uid" => $uid,
    "name" => $nama_user,
    "nidn" => $nidn_user,
    "role" => "dosen",
    "room" => $nama_ruangan,
    "subject" => $matched_sched['mata_kuliah']
]);
exit;

} else {
    // Tidak memiliki permission apa-apa
    $status_log = 'DENY';
    $keterangan = 'Role pengguna tidak memiliki izin akses apa pun';
    
    $stmt_log = $conn->prepare("INSERT INTO akses_log (id_kartu, scanned_uid, id_user, id_device, id_ruangan, waktu_akses, status, keterangan) VALUES (?, ?, ?, ?, ?, NOW(), ?, ?)");
    $stmt_log->bind_param("isiiiss", $id_kartu, $uid, $id_user, $id_device, $id_ruangan, $status_log, $keterangan);
    $stmt_log->execute();
    
    echo json_encode([
        "success" => false,
        "access" => "DENY",
        "message" => "Tdk Ada Akses",
        "uid" => $uid,
        "name" => $nama_user,
        "room" => $nama_ruangan
    ]);
    exit;
}