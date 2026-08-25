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
    $nama = trim($_POST['nama']);
    $nidn = trim($_POST['nidn']);
    $id_role = intval($_POST['id_role']);
    $status = $_POST['status'] ?? 'aktif';

    if ($nama == "" || $nidn == "" || $id_role == 0) {
        $error = "Semua field harus diisi.";
    } else {
        // Cek keunikan NIDN
        $cek = $conn->prepare("SELECT id_user FROM users WHERE nidn = ?");
        $cek->bind_param("s", $nidn);
        $cek->execute();
        $hasil = $cek->get_result();

        if ($hasil->num_rows > 0) {
            $error = "NIDN '$nidn' sudah terdaftar untuk dosen lain.";
        } else {
            $stmt = $conn->prepare("INSERT INTO users (nama, nidn, id_role, status) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssis", $nama, $nidn, $id_role, $status);
            if ($stmt->execute()) {
                header("Location: dosen.php?success=tambah");
                exit;
            } else {
                $error = "Gagal menyimpan data dosen.";
            }
        }
    }
}

/*==============================
UPDATE DATA
==============================*/
if (isset($_POST['update'])) {
    $id = intval($_POST['id_user']);
    $nama = trim($_POST['nama']);
    $nidn = trim($_POST['nidn']);
    $id_role = intval($_POST['id_role']);
    $status = $_POST['status'];

    if ($nama == "" || $nidn == "" || $id_role == 0) {
        $error = "Semua field harus diisi.";
    } else {
        // Cek keunikan NIDN selain dosen ini sendiri
        $cek = $conn->prepare("SELECT id_user FROM users WHERE nidn = ? AND id_user != ?");
        $cek->bind_param("si", $nidn, $id);
        $cek->execute();
        $hasil = $cek->get_result();

        if ($hasil->num_rows > 0) {
            $error = "NIDN '$nidn' sudah terdaftar untuk dosen lain.";
        } else {
            $stmt = $conn->prepare("UPDATE users SET nama = ?, nidn = ?, id_role = ?, status = ? WHERE id_user = ?");
            $stmt->bind_param("ssisi", $nama, $nidn, $id_role, $status, $id);
            if ($stmt->execute()) {
                header("Location: dosen.php?success=update");
                exit;
            } else {
                $error = "Gagal memperbarui data dosen.";
            }
        }
    }
}

/*==============================
HAPUS DATA
==============================*/
if ($aksi == "hapus") {
    $id = intval($_GET['id']);

    // Cek apakah dosen memiliki kartu RFID terdaftar
    $cek_kartu = $conn->query("SELECT COUNT(*) AS total FROM kartu WHERE id_user = $id")->fetch_assoc();
    // Cek apakah dosen terdaftar di jadwal akses
    $cek_jadwal = $conn->query("SELECT COUNT(*) AS total FROM jadwal WHERE id_user = $id")->fetch_assoc();

    if ($cek_kartu['total'] > 0) {
        header("Location: dosen.php?error=kartu");
        exit;
    } else if ($cek_jadwal['total'] > 0) {
        header("Location: dosen.php?error=jadwal");
        exit;
    } else {
        $stmt = $conn->prepare("DELETE FROM users WHERE id_user = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            header("Location: dosen.php?success=hapus");
            exit;
        } else {
            header("Location: dosen.php?error=hapus");
            exit;
        }
    }
}

// Notifikasi URL redirect
if (isset($_GET['success'])) {
    if ($_GET['success'] == 'tambah') $success = "Dosen baru berhasil ditambahkan!";
    if ($_GET['success'] == 'update') $success = "Data dosen berhasil diperbarui!";
    if ($_GET['success'] == 'hapus') $success = "Data dosen berhasil dihapus!";
}
if (isset($_GET['error'])) {
    if ($_GET['error'] == 'kartu') $error = "Dosen tidak bisa dihapus karena memiliki kartu RFID aktif.";
    if ($_GET['error'] == 'jadwal') $error = "Dosen tidak bisa dihapus karena memiliki jadwal akses.";
    if ($_GET['error'] == 'hapus') $error = "Gagal menghapus data dosen.";
}

// Mode Edit: Ambil data dosen yang akan diedit
$editData = null;
if ($aksi == 'edit' && isset($_GET['id'])) {
    $id_edit = intval($_GET['id']);
    $res = $conn->query("SELECT * FROM users WHERE id_user = $id_edit");
    if ($res->num_rows > 0) {
        $editData = $res->fetch_assoc();
    }
}

// Ambil data roles untuk dropdown
$roles = $conn->query("SELECT * FROM roles ORDER BY nama_role ASC");

// Ambil semua data dosen
$dosens = $conn->query("
    SELECT u.*, r.nama_role 
    FROM users u 
    LEFT JOIN roles r ON u.id_role = r.id_role 
    ORDER BY u.id_user DESC
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
                    <i class="fas <?= $editData ? 'fa-user-edit' : 'fa-user-plus' ?>" style="color: var(--primary);"></i>
                    <?= $editData ? 'Edit Data Dosen' : 'Tambah Dosen Baru' ?>
                </h2>
            </div>

            <form method="POST" action="dosen.php">
                <?php if ($editData): ?>
                    <input type="hidden" name="id_user" value="<?= $editData['id_user'] ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label class="form-label" for="nidn">NIDN</label>
                    <input 
                        type="text" 
                        class="form-control" 
                        id="nidn" 
                        name="nidn" 
                        placeholder="Masukkan NIDN Dosen" 
                        value="<?= $editData ? htmlspecialchars($editData['nidn']) : '' ?>" 
                        required
                    >
                </div>

                <div class="form-group">
                    <label class="form-label" for="nama">Nama Lengkap</label>
                    <input 
                        type="text" 
                        class="form-control" 
                        id="nama" 
                        name="nama" 
                        placeholder="Nama Lengkap dengan Gelar" 
                        value="<?= $editData ? htmlspecialchars($editData['nama']) : '' ?>" 
                        required
                    >
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="id_role">Role Pengguna</label>
                        <select class="form-control" id="id_role" name="id_role" required>
                            <option value="">-- Pilih Role --</option>
                            <?php if ($roles && $roles->num_rows > 0): ?>
                                <?php 
                                // Reset pointer ke awal
                                $roles->data_seek(0);
                                while ($role = $roles->fetch_assoc()): 
                                ?>
                                    <option value="<?= $role['id_role'] ?>" <?= ($editData && $editData['id_role'] == $role['id_role']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($role['nama_role']) ?>
                                    </option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="status">Status</label>
                        <select class="form-control" id="status" name="status">
                            <option value="aktif" <?= ($editData && $editData['status'] == 'aktif') ? 'selected' : '' ?>>Aktif</option>
                            <option value="nonaktif" <?= ($editData && $editData['status'] == 'nonaktif') ? 'selected' : '' ?>>Nonaktif</option>
                        </select>
                    </div>
                </div>

                <div class="form-actions">
                    <?php if ($editData): ?>
                        <a href="dosen.php" class="btn btn-secondary">Batal</a>
                        <button type="submit" name="update" class="btn btn-primary">
                            <i class="fas fa-save"></i> Perbarui
                        </button>
                    <?php else: ?>
                        <button type="submit" name="simpan" class="btn btn-success">
                            <i class="fas fa-plus"></i> Simpan Dosen
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
                <i class="fas fa-users" style="color: var(--primary);"></i>
                Daftar Dosen
            </h2>
        </div>

        <div class="table-responsive">
            <table class="modern-table">
                <thead>
                    <tr>
                        <th width="50" style="text-align: center;">No</th>
                        <th>NIDN</th>
                        <th>Nama Dosen</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th width="120" style="text-align: center;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($dosens && $dosens->num_rows > 0): $no = 1; ?>
                        <?php while ($row = $dosens->fetch_assoc()): ?>
                            <tr>
                                <td align="center"><?= $no++ ?></td>
                                <td><strong><?= htmlspecialchars($row['nidn']) ?></strong></td>
                                <td><?= htmlspecialchars($row['nama']) ?></td>
                                <td><span class="badge badge-info"><?= htmlspecialchars($row['nama_role'] ?? 'N/A') ?></span></td>
                                <td>
                                    <?php if ($row['status'] == 'aktif'): ?>
                                        <span class="badge badge-success">Aktif</span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary">Nonaktif</span>
                                    <?php endif; ?>
                                </td>
                                <td align="center">
                                    <div style="display: flex; gap: 8px; justify-content: center;">
                                        <a href="?aksi=edit&id=<?= $row['id_user'] ?>" class="btn btn-secondary btn-sm" title="Edit">
                                            <i class="fas fa-edit" style="color: #3b82f6;"></i>
                                        </a>
                                        <a href="?aksi=hapus&id=<?= $row['id_user'] ?>" class="btn btn-secondary btn-sm" title="Hapus" onclick="return confirm('Apakah Anda yakin ingin menghapus dosen \'<?= htmlspecialchars($row['nama']) ?>\'?')">
                                            <i class="fas fa-trash-alt" style="color: #ef4444;"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" align="center" style="color: var(--text-muted); padding: 25px;">
                                Belum ada data dosen. Silakan tambahkan dosen baru.
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
