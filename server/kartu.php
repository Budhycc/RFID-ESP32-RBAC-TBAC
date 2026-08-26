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
    $id_user = intval($_POST['id_user']);
    $uid = strtoupper(trim($_POST['uid']));
    $status = $_POST['status'] ?? 'aktif';

    if ($id_user == 0 || $uid == "") {
        $error = "Semua field harus diisi.";
    } else {
        // Cek keunikan UID
        $cek = $conn->prepare("SELECT id_kartu FROM kartu WHERE uid = ?");
        $cek->bind_param("s", $uid);
        $cek->execute();
        $hasil = $cek->get_result();

        if ($hasil->num_rows > 0) {
            $error = "UID '$uid' sudah terdaftar pada kartu lain.";
        } else {
            $stmt = $conn->prepare("INSERT INTO kartu (id_user, uid, status) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $id_user, $uid, $status);
            if ($stmt->execute()) {
                header("Location: kartu.php?success=tambah");
                exit;
            } else {
                $error = "Gagal menyimpan data kartu.";
            }
        }
    }
}

/*==============================
UPDATE DATA
==============================*/
if (isset($_POST['update'])) {
    $id = intval($_POST['id_kartu']);
    $id_user = intval($_POST['id_user']);
    $uid = strtoupper(trim($_POST['uid']));
    $status = $_POST['status'];

    if ($id_user == 0 || $uid == "") {
        $error = "Semua field harus diisi.";
    } else {
        // Cek keunikan UID selain kartu ini sendiri
        $cek = $conn->prepare("SELECT id_kartu FROM kartu WHERE uid = ? AND id_kartu != ?");
        $cek->bind_param("si", $uid, $id);
        $cek->execute();
        $hasil = $cek->get_result();

        if ($hasil->num_rows > 0) {
            $error = "UID '$uid' sudah terdaftar pada kartu lain.";
        } else {
            $stmt = $conn->prepare("UPDATE kartu SET id_user = ?, uid = ?, status = ? WHERE id_kartu = ?");
            $stmt->bind_param("issi", $id_user, $uid, $status, $id);
            if ($stmt->execute()) {
                header("Location: kartu.php?success=update");
                exit;
            } else {
                $error = "Gagal memperbarui data kartu.";
            }
        }
    }
}

/*==============================
HAPUS DATA
==============================*/
if ($aksi == "hapus") {
    $id = intval($_GET['id']);

    // Cek apakah kartu memiliki riwayat log akses
    $cek_log = $conn->query("SELECT COUNT(*) AS total FROM akses_log WHERE id_kartu = $id")->fetch_assoc();

    if ($cek_log['total'] > 0) {
        header("Location: kartu.php?error=log");
        exit;
    } else {
        $stmt = $conn->prepare("DELETE FROM kartu WHERE id_kartu = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            header("Location: kartu.php?success=hapus");
            exit;
        } else {
            header("Location: kartu.php?error=hapus");
            exit;
        }
    }
}

// Notifikasi URL redirect
if (isset($_GET['success'])) {
    if ($_GET['success'] == 'tambah') $success = "Kartu RFID baru berhasil diregistrasi!";
    if ($_GET['success'] == 'update') $success = "Data kartu RFID berhasil diperbarui!";
    if ($_GET['success'] == 'hapus') $success = "Data kartu RFID berhasil dihapus!";
}
if (isset($_GET['error'])) {
    if ($_GET['error'] == 'log') $error = "Kartu RFID tidak bisa dihapus karena memiliki riwayat log akses.";
    if ($_GET['error'] == 'hapus') $error = "Gagal menghapus data kartu RFID.";
}

// Mode Edit: Ambil data kartu yang akan diedit
$editData = null;
if ($aksi == 'edit' && isset($_GET['id'])) {
    $id_edit = intval($_GET['id']);
    $res = $conn->query("SELECT * FROM kartu WHERE id_kartu = $id_edit");
    if ($res->num_rows > 0) {
        $editData = $res->fetch_assoc();
    }
}

// Ambil data Dosen (User) untuk dropdown
$users = $conn->query("SELECT * FROM users ORDER BY nama ASC");

// Ambil semua data kartu
$kartus = $conn->query("
    SELECT k.*, u.nama, u.nidn 
    FROM kartu k 
    LEFT JOIN users u ON k.id_user = u.id_user 
    ORDER BY k.id_kartu DESC
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
                    <i class="fas <?= $editData ? 'fa-id-card-clip' : 'fa-id-card' ?>" style="color: var(--primary);"></i>
                    <?= $editData ? 'Edit Registrasi Kartu' : 'Registrasi Kartu Baru' ?>
                </h2>
            </div>

            <form method="POST" action="kartu.php">
                <?php if ($editData): ?>
                    <input type="hidden" name="id_kartu" value="<?= $editData['id_kartu'] ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label class="form-label" for="id_user">Pemilik Kartu (Dosen)</label>
                    <select class="form-control" id="id_user" name="id_user" required>
                        <option value="">-- Pilih Dosen --</option>
                        <?php if ($users && $users->num_rows > 0): ?>
                            <?php 
                            $users->data_seek(0);
                            while ($u = $users->fetch_assoc()): 
                            ?>
                                <option value="<?= $u['id_user'] ?>" <?= ($editData && $editData['id_user'] == $u['id_user']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($u['nama']) ?> (NIDN: <?= htmlspecialchars($u['nidn']) ?>)
                                </option>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="uid">UID Kartu RFID</label>
                    <div style="display: flex; gap: 10px;">
                        <input 
                            type="text" 
                            class="form-control" 
                            id="uid" 
                            name="uid" 
                            placeholder="Contoh: A3B2C5D7" 
                            value="<?= $editData ? htmlspecialchars($editData['uid']) : '' ?>" 
                            style="text-transform: uppercase;"
                            required
                        >
                        <button type="button" class="btn btn-secondary" id="btn-scan-uid" style="flex-shrink: 0;" title="Baca UID RFID yang saat ini ditempelkan ke reader">
                            <i class="fas fa-sync-alt"></i> Ambil UID
                        </button>
                    </div>
                    <small style="color: var(--text-muted); display: block; margin-top: 5px;">
                        Tempelkan kartu RFID pada reader, kemudian klik tombol <strong>Ambil UID</strong> untuk mendeteksi secara otomatis.
                    </small>
                </div>

                <div class="form-group">
                    <label class="form-label" for="status">Status Kartu</label>
                    <select class="form-control" id="status" name="status">
                        <option value="aktif" <?= ($editData && $editData['status'] == 'aktif') ? 'selected' : '' ?>>Aktif (Diberikan Akses)</option>
                        <option value="nonaktif" <?= ($editData && $editData['status'] == 'nonaktif') ? 'selected' : '' ?>>Nonaktif (Akses Diblokir)</option>
                    </select>
                </div>

                <div class="form-actions">
                    <?php if ($editData): ?>
                        <a href="kartu.php" class="btn btn-secondary">Batal</a>
                        <button type="submit" name="update" class="btn btn-primary">
                            <i class="fas fa-save"></i> Perbarui
                        </button>
                    <?php else: ?>
                        <button type="submit" name="simpan" class="btn btn-success">
                            <i class="fas fa-plus"></i> Registrasikan Kartu
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
                <i class="fas fa-id-card-list" style="color: var(--primary);"></i>
                Daftar Kartu RFID Terdaftar
            </h2>
        </div>

        <div class="table-responsive">
            <table class="modern-table">
                <thead>
                    <tr>
                        <th width="50" style="text-align: center;">No</th>
                        <th>UID Kartu</th>
                        <th>Nama Dosen</th>
                        <th>NIDN Dosen</th>
                        <th>Status</th>
                        <th width="120" style="text-align: center;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($kartus && $kartus->num_rows > 0): $no = 1; ?>
                        <?php while ($row = $kartus->fetch_assoc()): ?>
                            <tr>
                                <td align="center"><?= $no++ ?></td>
                                <td><code style="font-size: 14px; font-weight: bold; color: var(--primary); background-color: #f1f5f9; padding: 4px 8px; border-radius: 4px; border: 1px solid var(--border-color);"><?= htmlspecialchars($row['uid']) ?></code></td>
                                <td><?= htmlspecialchars($row['nama'] ?? 'Tidak Dikenal') ?></td>
                                <td><?= htmlspecialchars($row['nidn'] ?? 'N/A') ?></td>
                                <td>
                                    <?php if ($row['status'] == 'aktif'): ?>
                                        <span class="badge badge-success">Aktif</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Blokir</span>
                                    <?php endif; ?>
                                </td>
                                <td align="center">
                                    <div style="display: flex; gap: 8px; justify-content: center;">
                                        <a href="?aksi=edit&id=<?= $row['id_kartu'] ?>" class="btn btn-secondary btn-sm" title="Edit">
                                            <i class="fas fa-edit" style="color: #3b82f6;"></i>
                                        </a>
                                        <a href="?aksi=hapus&id=<?= $row['id_kartu'] ?>" class="btn btn-secondary btn-sm" title="Hapus" onclick="return confirm('Apakah Anda yakin ingin menghapus kartu UID \'<?= htmlspecialchars($row['uid']) ?>\'?')">
                                            <i class="fas fa-trash-alt" style="color: #ef4444;"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" align="center" style="color: var(--text-muted); padding: 25px;">
                                Belum ada kartu RFID terdaftar.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.getElementById('btn-scan-uid').addEventListener('click', function() {
    var originalHTML = this.innerHTML;
    this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Membaca...';
    this.disabled = true;
    
    fetch('api/last_scan.php?' + Date.now())
    .then(res => res.json())
    .then(data => {
        if(data.uid) {
            if (data.registered === true) {
                alert('Peringatan: Kartu dengan UID ' + data.uid.toUpperCase() + ' sudah terdaftar di sistem!');
                document.getElementById('uid').value = ''; // Kosongkan form jika sudah terdaftar
            } else {
                document.getElementById('uid').value = data.uid.toUpperCase();
            }
        } else {
            alert('Tidak ada kartu terdeteksi baru-baru ini. Tempelkan kartu pada reader terlebih dahulu.');
        }
    })
    .catch(err => {
        console.error('Error fetching last scan UID:', err);
        alert('Terjadi kesalahan koneksi saat membaca scanner RFID.');
    })
    .finally(() => {
        this.innerHTML = originalHTML;
        this.disabled = false;
    });
});
</script>

<?php
include "include/footer.php";
?>
