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
    $nama_ruangan = trim($_POST['nama_ruangan']);
    $status = $_POST['status'] ?? 'aktif';

    if ($nama_ruangan == "") {
        $error = "Nama ruangan tidak boleh kosong.";
    } else {
        // Cek keunikan nama ruangan
        $cek = $conn->prepare("SELECT id_ruangan FROM ruangan WHERE nama_ruangan = ?");
        $cek->bind_param("s", $nama_ruangan);
        $cek->execute();
        $hasil = $cek->get_result();

        if ($hasil->num_rows > 0) {
            $error = "Ruangan dengan nama '$nama_ruangan' sudah terdaftar.";
        } else {
            $stmt = $conn->prepare("INSERT INTO ruangan (nama_ruangan, status) VALUES (?, ?)");
            $stmt->bind_param("ss", $nama_ruangan, $status);
            if ($stmt->execute()) {
                header("Location: ruangan.php?success=tambah");
                exit;
            } else {
                $error = "Gagal menyimpan data ruangan.";
            }
        }
    }
}

/*==============================
UPDATE DATA
==============================*/
if (isset($_POST['update'])) {
    $id = intval($_POST['id_ruangan']);
    $nama_ruangan = trim($_POST['nama_ruangan']);
    $status = $_POST['status'];

    if ($nama_ruangan == "") {
        $error = "Nama ruangan tidak boleh kosong.";
    } else {
        // Cek keunikan nama ruangan selain ruangan ini sendiri
        $cek = $conn->prepare("SELECT id_ruangan FROM ruangan WHERE nama_ruangan = ? AND id_ruangan != ?");
        $cek->bind_param("si", $nama_ruangan, $id);
        $cek->execute();
        $hasil = $cek->get_result();

        if ($hasil->num_rows > 0) {
            $error = "Ruangan dengan nama '$nama_ruangan' sudah terdaftar.";
        } else {
            $stmt = $conn->prepare("UPDATE ruangan SET nama_ruangan = ?, status = ? WHERE id_ruangan = ?");
            $stmt->bind_param("ssi", $nama_ruangan, $status, $id);
            if ($stmt->execute()) {
                header("Location: ruangan.php?success=update");
                exit;
            } else {
                $error = "Gagal memperbarui data ruangan.";
            }
        }
    }
}

/*==============================
HAPUS DATA
==============================*/
if ($aksi == "hapus") {
    $id = intval($_GET['id']);

    // Cek apakah ruangan memiliki device reader terdaftar
    $cek_device = $conn->query("SELECT COUNT(*) AS total FROM devices WHERE id_ruangan = $id")->fetch_assoc();
    // Cek apakah ruangan terdaftar di jadwal kelas
    $cek_jadwal = $conn->query("SELECT COUNT(*) AS total FROM jadwal WHERE id_ruangan = $id")->fetch_assoc();

    if ($cek_device['total'] > 0) {
        header("Location: ruangan.php?error=device");
        exit;
    } else if ($cek_jadwal['total'] > 0) {
        header("Location: ruangan.php?error=jadwal");
        exit;
    } else {
        $stmt = $conn->prepare("DELETE FROM ruangan WHERE id_ruangan = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            header("Location: ruangan.php?success=hapus");
            exit;
        } else {
            header("Location: ruangan.php?error=hapus");
            exit;
        }
    }
}

// Notifikasi URL redirect
if (isset($_GET['success'])) {
    if ($_GET['success'] == 'tambah') $success = "Ruangan baru berhasil ditambahkan!";
    if ($_GET['success'] == 'update') $success = "Data ruangan berhasil diperbarui!";
    if ($_GET['success'] == 'hapus') $success = "Data ruangan berhasil dihapus!";
}
if (isset($_GET['error'])) {
    if ($_GET['error'] == 'device') $error = "Ruangan tidak dapat dihapus karena terdapat device RFID terpasang.";
    if ($_GET['error'] == 'jadwal') $error = "Ruangan tidak dapat dihapus karena digunakan pada jadwal kelas.";
    if ($_GET['error'] == 'hapus') $error = "Gagal menghapus data ruangan.";
}

// Mode Edit: Ambil data ruangan yang akan diedit
$editData = null;
if ($aksi == 'edit' && isset($_GET['id'])) {
    $id_edit = intval($_GET['id']);
    $res = $conn->query("SELECT * FROM ruangan WHERE id_ruangan = $id_edit");
    if ($res->num_rows > 0) {
        $editData = $res->fetch_assoc();
    }
}

// Ambil semua data ruangan
$ruangans = $conn->query("SELECT * FROM ruangan ORDER BY id_ruangan ASC");

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
                    <?= $editData ? 'Edit Data Ruangan' : 'Tambah Ruangan Baru' ?>
                </h2>
            </div>

            <form method="POST" action="ruangan.php">
                <?php if ($editData): ?>
                    <input type="hidden" name="id_ruangan" value="<?= $editData['id_ruangan'] ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label class="form-label" for="nama_ruangan">Nama Ruangan</label>
                    <input 
                        type="text" 
                        class="form-control" 
                        id="nama_ruangan" 
                        name="nama_ruangan" 
                        placeholder="Contoh: Lab Jaringan, R. Teori 1" 
                        value="<?= $editData ? htmlspecialchars($editData['nama_ruangan']) : '' ?>" 
                        required
                    >
                </div>

                <div class="form-group">
                    <label class="form-label" for="status">Status Akses Ruangan</label>
                    <select class="form-control" id="status" name="status">
                        <option value="aktif" <?= ($editData && $editData['status'] == 'aktif') ? 'selected' : '' ?>>Aktif (Dapat Diakses)</option>
                        <option value="nonaktif" <?= ($editData && $editData['status'] == 'nonaktif') ? 'selected' : '' ?>>Nonaktif (Terkunci/Maintenance)</option>
                    </select>
                </div>

                <div class="form-actions">
                    <?php if ($editData): ?>
                        <a href="ruangan.php" class="btn btn-secondary">Batal</a>
                        <button type="submit" name="update" class="btn btn-primary">
                            <i class="fas fa-save"></i> Perbarui
                        </button>
                    <?php else: ?>
                        <button type="submit" name="simpan" class="btn btn-success">
                            <i class="fas fa-plus"></i> Tambah Ruangan
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
                <i class="fas fa-door-closed" style="color: var(--primary);"></i>
                Daftar Ruangan
            </h2>
        </div>

        <div class="table-responsive">
            <table class="modern-table">
                <thead>
                    <tr>
                        <th width="80" style="text-align: center;">No</th>
                        <th>Nama Ruangan</th>
                        <th>Status</th>
                        <th width="150" style="text-align: center;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($ruangans && $ruangans->num_rows > 0): $no = 1; ?>
                        <?php while ($row = $ruangans->fetch_assoc()): ?>
                            <tr>
                                <td align="center"><?= $no++ ?></td>
                                <td><strong><?= htmlspecialchars($row['nama_ruangan']) ?></strong></td>
                                <td>
                                    <?php if ($row['status'] == 'aktif'): ?>
                                        <span class="badge badge-success">Aktif</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Nonaktif</span>
                                    <?php endif; ?>
                                </td>
                                <td align="center">
                                    <div style="display: flex; gap: 8px; justify-content: center;">
                                        <a href="?aksi=edit&id=<?= $row['id_ruangan'] ?>" class="btn btn-secondary btn-sm" title="Edit">
                                            <i class="fas fa-edit" style="color: #3b82f6;"></i>
                                        </a>
                                        <a href="?aksi=hapus&id=<?= $row['id_ruangan'] ?>" class="btn btn-secondary btn-sm" title="Hapus" onclick="return confirm('Apakah Anda yakin ingin menghapus ruangan \'<?= htmlspecialchars($row['nama_ruangan']) ?>\'?')">
                                            <i class="fas fa-trash-alt" style="color: #ef4444;"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" align="center" style="color: var(--text-muted); padding: 20px;">
                                Belum ada data ruangan.
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
