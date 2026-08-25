<?php
include "session_check.php";
include "config.php";

$aksi = $_GET['aksi'] ?? '';
$error = '';
$success = '';

/*==============================
TAMBAH DATA
==============================*/
if (isset($_POST['simpan'])) {
    $device_id = strtoupper(trim($_POST['device_id']));
    $nama_device = trim($_POST['nama_device']);
    $id_ruangan = intval($_POST['id_ruangan']);
    $status = $_POST['status'] ?? 'aktif';

    if ($device_id == "" || $nama_device == "" || $id_ruangan == 0) {
        $error = "Semua field harus diisi.";
    } else {
        // Cek keunikan Device ID
        $cek = $conn->prepare("SELECT id_device FROM devices WHERE device_id = ?");
        $cek->bind_param("s", $device_id);
        $cek->execute();
        $hasil = $cek->get_result();

        if ($hasil->num_rows > 0) {
            $error = "Device ID '$device_id' sudah terdaftar.";
        } else {
            $stmt = $conn->prepare("INSERT INTO devices (device_id, nama_device, id_ruangan, status) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssis", $device_id, $nama_device, $id_ruangan, $status);
            if ($stmt->execute()) {
                header("Location: device.php?success=tambah");
                exit;
            } else {
                $error = "Gagal menyimpan data device.";
            }
        }
    }
}

/*==============================
UPDATE DATA
==============================*/
if (isset($_POST['update'])) {
    $id = intval($_POST['id_device']);
    $device_id = strtoupper(trim($_POST['device_id']));
    $nama_device = trim($_POST['nama_device']);
    $id_ruangan = intval($_POST['id_ruangan']);
    $status = $_POST['status'];

    if ($device_id == "" || $nama_device == "" || $id_ruangan == 0) {
        $error = "Semua field harus diisi.";
    } else {
        // Cek keunikan Device ID selain device ini sendiri
        $cek = $conn->prepare("SELECT id_device FROM devices WHERE device_id = ? AND id_device != ?");
        $cek->bind_param("si", $device_id, $id);
        $cek->execute();
        $hasil = $cek->get_result();

        if ($hasil->num_rows > 0) {
            $error = "Device ID '$device_id' sudah terdaftar.";
        } else {
            $stmt = $conn->prepare("UPDATE devices SET device_id = ?, nama_device = ?, id_ruangan = ?, status = ? WHERE id_device = ?");
            $stmt->bind_param("ssisi", $device_id, $nama_device, $id_ruangan, $status, $id);
            if ($stmt->execute()) {
                header("Location: device.php?success=update");
                exit;
            } else {
                $error = "Gagal memperbarui data device.";
            }
        }
    }
}

/*==============================
HAPUS DATA
==============================*/
if ($aksi == "hapus") {
    $id = intval($_GET['id']);

    // Cek apakah device memiliki riwayat log akses
    $cek_log = $conn->query("SELECT COUNT(*) AS total FROM akses_log WHERE id_device = $id")->fetch_assoc();

    if ($cek_log['total'] > 0) {
        header("Location: device.php?error=log");
        exit;
    } else {
        $stmt = $conn->prepare("DELETE FROM devices WHERE id_device = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            header("Location: device.php?success=hapus");
            exit;
        } else {
            header("Location: device.php?error=hapus");
            exit;
        }
    }
}

// Notifikasi URL redirect
if (isset($_GET['success'])) {
    if ($_GET['success'] == 'tambah') $success = "Device baru berhasil ditambahkan!";
    if ($_GET['success'] == 'update') $success = "Data device berhasil diperbarui!";
    if ($_GET['success'] == 'hapus') $success = "Data device berhasil dihapus!";
}
if (isset($_GET['error'])) {
    if ($_GET['error'] == 'log') $error = "Device tidak dapat dihapus karena memiliki riwayat log akses.";
    if ($_GET['error'] == 'hapus') $error = "Gagal menghapus data device.";
}

// Mode Edit: Ambil data device yang akan diedit
$editData = null;
if ($aksi == 'edit' && isset($_GET['id'])) {
    $id_edit = intval($_GET['id']);
    $res = $conn->query("SELECT * FROM devices WHERE id_device = $id_edit");
    if ($res->num_rows > 0) {
        $editData = $res->fetch_assoc();
    }
}

// Ambil data Ruangan untuk dropdown
$ruangans = $conn->query("SELECT * FROM ruangan WHERE status = 'aktif' ORDER BY nama_ruangan ASC");

// Ambil semua data device
$devices = $conn->query("
    SELECT d.*, r.nama_ruangan 
    FROM devices d 
    LEFT JOIN ruangan r ON d.id_ruangan = r.id_ruangan 
    ORDER BY d.id_device DESC
");

include "include/header.php";
?>

<!-- Alert Notifikasi -->
<?php if ($error): ?>
    <div style="background-color: #fee2e2; border-left: 4px solid #ef4444; color: #991b1b; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; display: flex; align-items: center; gap: 10px;">
        <i class="fas fa-exclamation-circle"></i>
        <span><?= htmlspecialchars($error) ?></span>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div style="background-color: #d1fae5; border-left: 4px solid #10b981; color: #065f46; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; display: flex; align-items: center; gap: 10px;">
        <i class="fas fa-check-circle"></i>
        <span><?= htmlspecialchars($success) ?></span>
    </div>
<?php endif; ?>

<div class="grid-2">
    <!-- Kolom Kiri: Form Input -->
    <div>
        <div class="panel">
            <div class="panel-header">
                <h2 class="panel-title">
                    <i class="fas <?= $editData ? 'fa-edit' : 'fa-plus-circle' ?>" style="color: var(--primary);"></i>
                    <?= $editData ? 'Edit Data Device' : 'Registrasi Device Baru' ?>
                </h2>
            </div>

            <form method="POST" action="device.php">
                <?php if ($editData): ?>
                    <input type="hidden" name="id_device" value="<?= $editData['id_device'] ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label class="form-label" for="device_id">Device ID (Kode Unik / MAC)</label>
                    <input 
                        type="text" 
                        class="form-control" 
                        id="device_id" 
                        name="device_id" 
                        placeholder="Contoh: ESP32-ROOM-01" 
                        value="<?= $editData ? htmlspecialchars($editData['device_id']) : '' ?>" 
                        style="text-transform: uppercase;"
                        required
                    >
                </div>

                <div class="form-group">
                    <label class="form-label" for="nama_device">Nama / Deskripsi Device</label>
                    <input 
                        type="text" 
                        class="form-control" 
                        id="nama_device" 
                        name="nama_device" 
                        placeholder="Contoh: Reader RFID Pintu Utama" 
                        value="<?= $editData ? htmlspecialchars($editData['nama_device']) : '' ?>" 
                        required
                    >
                </div>

                <div class="form-group">
                    <label class="form-label" for="id_ruangan">Lokasi Ruangan</label>
                    <select class="form-control" id="id_ruangan" name="id_ruangan" required>
                        <option value="">-- Pilih Ruangan --</option>
                        <?php if ($ruangans && $ruangans->num_rows > 0): ?>
                            <?php 
                            $ruangans->data_seek(0);
                            while ($r = $ruangans->fetch_assoc()): 
                            ?>
                                <option value="<?= $r['id_ruangan'] ?>" <?= ($editData && $editData['id_ruangan'] == $r['id_ruangan']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($r['nama_ruangan']) ?>
                                </option>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="status">Status Device</label>
                    <select class="form-control" id="status" name="status">
                        <option value="aktif" <?= ($editData && $editData['status'] == 'aktif') ? 'selected' : '' ?>>Aktif (Dapat Menerima Scan)</option>
                        <option value="nonaktif" <?= ($editData && $editData['status'] == 'nonaktif') ? 'selected' : '' ?>>Nonaktif (Mati / Blokir)</option>
                    </select>
                </div>

                <div class="form-actions">
                    <?php if ($editData): ?>
                        <a href="device.php" class="btn btn-secondary">Batal</a>
                        <button type="submit" name="update" class="btn btn-primary">
                            <i class="fas fa-save"></i> Perbarui
                        </button>
                    <?php else: ?>
                        <button type="submit" name="simpan" class="btn btn-success">
                            <i class="fas fa-plus"></i> Registrasi Device
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Kolom Kanan: Tabel Data -->
    <div class="panel">
        <div class="panel-header">
            <h2 class="panel-title">
                <i class="fas fa-microchip" style="color: var(--primary);"></i>
                Daftar Device Reader Terdaftar
            </h2>
        </div>

        <div class="table-responsive">
            <table class="modern-table">
                <thead>
                    <tr>
                        <th width="50" style="text-align: center;">No</th>
                        <th>Device ID</th>
                        <th>Deskripsi</th>
                        <th>Lokasi</th>
                        <th>Status</th>
                        <th width="120" style="text-align: center;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($devices && $devices->num_rows > 0): $no = 1; ?>
                        <?php while ($row = $devices->fetch_assoc()): ?>
                            <tr>
                                <td align="center"><?= $no++ ?></td>
                                <td><code style="font-weight: bold; background-color: #f1f5f9; padding: 4px 8px; border-radius: 4px; border: 1px solid var(--border-color);"><?= htmlspecialchars($row['device_id']) ?></code></td>
                                <td><?= htmlspecialchars($row['nama_device']) ?></td>
                                <td><span class="badge badge-info"><?= htmlspecialchars($row['nama_ruangan'] ?? 'N/A') ?></span></td>
                                <td>
                                    <?php if ($row['status'] == 'aktif'): ?>
                                        <span class="badge badge-success">Aktif</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Nonaktif</span>
                                    <?php endif; ?>
                                </td>
                                <td align="center">
                                    <div style="display: flex; gap: 8px; justify-content: center;">
                                        <a href="?aksi=edit&id=<?= $row['id_device'] ?>" class="btn btn-secondary btn-sm" title="Edit">
                                            <i class="fas fa-edit" style="color: #3b82f6;"></i>
                                        </a>
                                        <a href="?aksi=hapus&id=<?= $row['id_device'] ?>" class="btn btn-secondary btn-sm" title="Hapus" onclick="return confirm('Apakah Anda yakin ingin menghapus device ID \'<?= htmlspecialchars($row['device_id']) ?>\'?')">
                                            <i class="fas fa-trash-alt" style="color: #ef4444;"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" align="center" style="color: var(--text-muted); padding: 25px;">
                                Belum ada device terdaftar.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
include "include/footer.php";
?>
