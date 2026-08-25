<?php
include "session_check.php";
include "config.php";

$aksi = $_GET['aksi'] ?? '';
$error = '';
$success = '';

// Helper Hari
function getHariName($hari) {
    $days = [
        1 => 'Senin',
        2 => 'Selasa',
        3 => 'Rabu',
        4 => 'Kamis',
        5 => 'Jumat',
        6 => 'Sabtu',
        7 => 'Minggu'
    ];
    return $days[$hari] ?? 'N/A';
}

/*==============================
TAMBAH DATA
==============================*/
if (isset($_POST['simpan'])) {
    $id_user = intval($_POST['id_user']);
    $id_ruangan = intval($_POST['id_ruangan']);
    $hari = intval($_POST['hari']);
    $jam_masuk = trim($_POST['jam_masuk']);
    $jam_keluar = trim($_POST['jam_keluar']);
    $mata_kuliah = trim($_POST['mata_kuliah']);

    if ($id_user == 0 || $id_ruangan == 0 || $hari == 0 || $jam_masuk == "" || $jam_keluar == "" || $mata_kuliah == "") {
        $error = "Semua field harus diisi.";
    } else if (strtotime($jam_masuk) >= strtotime($jam_keluar)) {
        $error = "Jam masuk tidak boleh sama atau setelah jam keluar.";
    } else {
        $stmt = $conn->prepare("INSERT INTO jadwal (id_user, id_ruangan, hari, jam_masuk, jam_keluar, mata_kuliah) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iiisss", $id_user, $id_ruangan, $hari, $jam_masuk, $jam_keluar, $mata_kuliah);
        if ($stmt->execute()) {
            header("Location: jadwal.php?success=tambah");
            exit;
        } else {
            $error = "Gagal menyimpan jadwal akses.";
        }
    }
}

/*==============================
UPDATE DATA
==============================*/
if (isset($_POST['update'])) {
    $id = intval($_POST['id_jadwal']);
    $id_user = intval($_POST['id_user']);
    $id_ruangan = intval($_POST['id_ruangan']);
    $hari = intval($_POST['hari']);
    $jam_masuk = trim($_POST['jam_masuk']);
    $jam_keluar = trim($_POST['jam_keluar']);
    $mata_kuliah = trim($_POST['mata_kuliah']);

    if ($id_user == 0 || $id_ruangan == 0 || $hari == 0 || $jam_masuk == "" || $jam_keluar == "" || $mata_kuliah == "") {
        $error = "Semua field harus diisi.";
    } else if (strtotime($jam_masuk) >= strtotime($jam_keluar)) {
        $error = "Jam masuk tidak boleh sama atau setelah jam keluar.";
    } else {
        $stmt = $conn->prepare("UPDATE jadwal SET id_user = ?, id_ruangan = ?, hari = ?, jam_masuk = ?, jam_keluar = ?, mata_kuliah = ? WHERE id_jadwal = ?");
        $stmt->bind_param("iiisssi", $id_user, $id_ruangan, $hari, $jam_masuk, $jam_keluar, $mata_kuliah, $id);
        if ($stmt->execute()) {
            header("Location: jadwal.php?success=update");
            exit;
        } else {
            $error = "Gagal memperbarui jadwal akses.";
        }
    }
}

/*==============================
HAPUS DATA
==============================*/
if ($aksi == "hapus") {
    $id = intval($_GET['id']);
    $stmt = $conn->prepare("DELETE FROM jadwal WHERE id_jadwal = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        header("Location: jadwal.php?success=hapus");
        exit;
    } else {
        header("Location: jadwal.php?error=hapus");
        exit;
    }
}

// Notifikasi URL redirect
if (isset($_GET['success'])) {
    if ($_GET['success'] == 'tambah') $success = "Jadwal akses baru berhasil ditambahkan!";
    if ($_GET['success'] == 'update') $success = "Jadwal akses berhasil diperbarui!";
    if ($_GET['success'] == 'hapus') $success = "Jadwal akses berhasil dihapus!";
}
if (isset($_GET['error'])) {
    if ($_GET['error'] == 'hapus') $error = "Gagal menghapus jadwal akses.";
}

// Mode Edit: Ambil data jadwal yang akan diedit
$editData = null;
if ($aksi == 'edit' && isset($_GET['id'])) {
    $id_edit = intval($_GET['id']);
    $res = $conn->query("SELECT * FROM jadwal WHERE id_jadwal = $id_edit");
    if ($res->num_rows > 0) {
        $editData = $res->fetch_assoc();
    }
}

// Ambil data Dosen (User) dan Ruangan untuk dropdown
$users = $conn->query("SELECT * FROM users WHERE status = 'aktif' ORDER BY nama ASC");
$ruangans = $conn->query("SELECT * FROM ruangan WHERE status = 'aktif' ORDER BY nama_ruangan ASC");

// Ambil semua data jadwal
$jadwals = $conn->query("
    SELECT j.*, u.nama AS nama_dosen, r.nama_ruangan 
    FROM jadwal j 
    LEFT JOIN users u ON j.id_user = u.id_user 
    LEFT JOIN ruangan r ON j.id_ruangan = r.id_ruangan 
    ORDER BY r.nama_ruangan ASC, j.hari ASC, j.jam_masuk ASC
");

$grouped_jadwals = [];
if ($jadwals && $jadwals->num_rows > 0) {
    while ($row = $jadwals->fetch_assoc()) {
        $ruangan = $row['nama_ruangan'] ?? 'Ruangan Tidak Diketahui';
        if (!isset($grouped_jadwals[$ruangan])) {
            $grouped_jadwals[$ruangan] = [];
        }
        $grouped_jadwals[$ruangan][] = $row;
    }
}

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
                    <i class="fas <?= $editData ? 'fa-calendar-check' : 'fa-calendar-plus' ?>" style="color: var(--primary);"></i>
                    <?= $editData ? 'Edit Jadwal Akses' : 'Tambah Jadwal Akses' ?>
                </h2>
            </div>

            <form method="POST" action="jadwal.php">
                <?php if ($editData): ?>
                    <input type="hidden" name="id_jadwal" value="<?= $editData['id_jadwal'] ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label class="form-label" for="mata_kuliah">Mata Kuliah / Keterangan</label>
                    <input 
                        type="text" 
                        class="form-control" 
                        id="mata_kuliah" 
                        name="mata_kuliah" 
                        placeholder="Contoh: Pemrograman Web II, Pertemuan Dosen" 
                        value="<?= $editData ? htmlspecialchars($editData['mata_kuliah']) : '' ?>" 
                        required
                    >
                </div>

                <div class="form-group">
                    <label class="form-label" for="id_user">Dosen Pengajar</label>
                    <select class="form-control" id="id_user" name="id_user" required>
                        <option value="">-- Pilih Dosen --</option>
                        <?php if ($users && $users->num_rows > 0): ?>
                            <?php 
                            $users->data_seek(0);
                            while ($u = $users->fetch_assoc()): 
                            ?>
                                <option value="<?= $u['id_user'] ?>" <?= ($editData && $editData['id_user'] == $u['id_user']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($u['nama']) ?>
                                </option>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="id_ruangan">Ruangan Kelas</label>
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
                    <label class="form-label" for="hari">Hari</label>
                    <select class="form-control" id="hari" name="hari" required>
                        <option value="">-- Pilih Hari --</option>
                        <option value="1" <?= ($editData && $editData['hari'] == 1) ? 'selected' : '' ?>>Senin</option>
                        <option value="2" <?= ($editData && $editData['hari'] == 2) ? 'selected' : '' ?>>Selasa</option>
                        <option value="3" <?= ($editData && $editData['hari'] == 3) ? 'selected' : '' ?>>Rabu</option>
                        <option value="4" <?= ($editData && $editData['hari'] == 4) ? 'selected' : '' ?>>Kamis</option>
                        <option value="5" <?= ($editData && $editData['hari'] == 5) ? 'selected' : '' ?>>Jumat</option>
                        <option value="6" <?= ($editData && $editData['hari'] == 6) ? 'selected' : '' ?>>Sabtu</option>
                        <option value="7" <?= ($editData && $editData['hari'] == 7) ? 'selected' : '' ?>>Minggu</option>
                    </select>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="jam_masuk">Jam Mulai</label>
                        <input 
                            type="text" 
                            class="form-control timepicker" 
                            id="jam_masuk" 
                            name="jam_masuk" 
                            value="<?= $editData ? htmlspecialchars(date("H:i", strtotime($editData['jam_masuk']))) : '' ?>" 
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="jam_keluar">Jam Selesai</label>
                        <input 
                            type="text" 
                            class="form-control timepicker" 
                            id="jam_keluar" 
                            name="jam_keluar" 
                            value="<?= $editData ? htmlspecialchars(date("H:i", strtotime($editData['jam_keluar']))) : '' ?>" 
                            required
                        >
                    </div>
                </div>

                <div class="form-actions">
                    <?php if ($editData): ?>
                        <a href="jadwal.php" class="btn btn-secondary">Batal</a>
                        <button type="submit" name="update" class="btn btn-primary">
                            <i class="fas fa-save"></i> Perbarui
                        </button>
                    <?php else: ?>
                        <button type="submit" name="simpan" class="btn btn-success">
                            <i class="fas fa-plus"></i> Simpan Jadwal
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Kolom Kanan: Tabel Data -->
    <div style="display: flex; flex-direction: column; gap: 20px;">
        <?php if (!empty($grouped_jadwals)): ?>
            <?php foreach ($grouped_jadwals as $nama_ruangan => $list_jadwal): ?>
                <div class="panel">
                    <div class="panel-header" style="background-color: #f1f5f9; border-bottom: 2px solid var(--primary);">
                        <h2 class="panel-title" style="font-size: 1.1rem;">
                            <i class="fas fa-door-open" style="color: var(--primary);"></i>
                            Ruangan: <?= htmlspecialchars($nama_ruangan) ?>
                        </h2>
                    </div>
                    <div class="table-responsive">
                        <table class="modern-table">
                            <thead>
                                <tr>
                                    <th width="50" style="text-align: center;">No</th>
                                    <th>Mata Kuliah</th>
                                    <th>Dosen</th>
                                    <th>Hari</th>
                                    <th>Waktu</th>
                                    <th width="100" style="text-align: center;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $no = 1; foreach ($list_jadwal as $row): ?>
                                    <tr>
                                        <td align="center"><?= $no++ ?></td>
                                        <td><strong><?= htmlspecialchars($row['mata_kuliah']) ?></strong></td>
                                        <td><?= htmlspecialchars($row['nama_dosen'] ?? 'N/A') ?></td>
                                        <td><?= getHariName($row['hari']) ?></td>
                                        <td>
                                            <code style="font-weight: 600; color: #475569;">
                                                <?= date("H:i", strtotime($row['jam_masuk'])) ?> - <?= date("H:i", strtotime($row['jam_keluar'])) ?>
                                            </code>
                                        </td>
                                        <td align="center">
                                            <div style="display: flex; gap: 8px; justify-content: center;">
                                                <a href="?aksi=edit&id=<?= $row['id_jadwal'] ?>" class="btn btn-secondary btn-sm" title="Edit">
                                                    <i class="fas fa-edit" style="color: #3b82f6;"></i>
                                                </a>
                                                <a href="?aksi=hapus&id=<?= $row['id_jadwal'] ?>" class="btn btn-secondary btn-sm" title="Hapus" onclick="return confirm('Apakah Anda yakin ingin menghapus jadwal kuliah \'<?= htmlspecialchars($row['mata_kuliah']) ?>\'?')">
                                                    <i class="fas fa-trash-alt" style="color: #ef4444;"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="panel">
                <div class="panel-header">
                    <h2 class="panel-title">
                        <i class="fas fa-calendar-alt" style="color: var(--primary);"></i>
                        Daftar Jadwal Akses Kelas
                    </h2>
                </div>
                <div style="padding: 25px; text-align: center; color: var(--text-muted);">
                    Belum ada data jadwal akses.
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
include "include/footer.php";
?>
