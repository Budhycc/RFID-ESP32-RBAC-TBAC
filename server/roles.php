<?php
include "session_check.php";
include "config.php";

$aksi = $_GET['aksi'] ?? '';
$error = '';
$success = '';

// Ambil daftar permissions
$perms_res = $conn->query("SELECT * FROM permissions ORDER BY id_permission ASC");
$all_permissions = [];
$perm_operational_id = 0;
while($p = $perms_res->fetch_assoc()){
    $all_permissions[] = $p;
    if($p['nama_permission'] == 'access_operational') {
        $perm_operational_id = $p['id_permission'];
    }
}

/*==============================
TAMBAH DATA
==============================*/
if (isset($_POST['simpan'])) {
    $nama_role = trim($_POST['nama_role']);
    $permissions_checked = $_POST['permissions'] ?? [];
    
    $jam_mulai = (in_array($perm_operational_id, $permissions_checked) && !empty($_POST['jam_mulai'])) ? $_POST['jam_mulai'] : null;
    $jam_akhir = (in_array($perm_operational_id, $permissions_checked) && !empty($_POST['jam_akhir'])) ? $_POST['jam_akhir'] : null;

    if ($nama_role == "") {
        $error = "Nama role tidak boleh kosong.";
    } else {
        $cek = $conn->prepare("SELECT id_role FROM roles WHERE nama_role = ?");
        $cek->bind_param("s", $nama_role);
        $cek->execute();
        $hasil = $cek->get_result();
        
        if ($hasil->num_rows > 0) {
            $error = "Role '$nama_role' sudah ada.";
        } else {
            $stmt = $conn->prepare("INSERT INTO roles (nama_role, jam_mulai, jam_akhir) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $nama_role, $jam_mulai, $jam_akhir);
            if ($stmt->execute()) {
                $new_role_id = $conn->insert_id;
                $stmt_perm = $conn->prepare("INSERT INTO role_permissions (id_role, id_permission) VALUES (?, ?)");
                foreach ($permissions_checked as $pid) {
                    $stmt_perm->bind_param("ii", $new_role_id, $pid);
                    $stmt_perm->execute();
                }
                header("Location: roles.php?success=tambah");
                exit;
            } else {
                $error = "Gagal menyimpan data.";
            }
        }
    }
}

/*==============================
UPDATE DATA
==============================*/
if (isset($_POST['update'])) {
    $id = intval($_POST['id_role']);
    $nama = trim($_POST['nama_role']);
    $permissions_checked = $_POST['permissions'] ?? [];
    
    $jam_mulai = (in_array($perm_operational_id, $permissions_checked) && !empty($_POST['jam_mulai'])) ? $_POST['jam_mulai'] : null;
    $jam_akhir = (in_array($perm_operational_id, $permissions_checked) && !empty($_POST['jam_akhir'])) ? $_POST['jam_akhir'] : null;
    
    if ($nama == "") {
        $error = "Nama role tidak boleh kosong.";
    } else {
        $cek = $conn->prepare("SELECT id_role FROM roles WHERE nama_role = ? AND id_role != ?");
        $cek->bind_param("si", $nama, $id);
        $cek->execute();
        $hasil = $cek->get_result();
        
        if ($hasil->num_rows > 0) {
            $error = "Role '$nama' sudah digunakan.";
        } else {
            $stmt = $conn->prepare("UPDATE roles SET nama_role = ?, jam_mulai = ?, jam_akhir = ? WHERE id_role = ?");
            $stmt->bind_param("sssi", $nama, $jam_mulai, $jam_akhir, $id);
            if ($stmt->execute()) {
                // Delete old permissions
                $conn->query("DELETE FROM role_permissions WHERE id_role = $id");
                // Insert new permissions
                $stmt_perm = $conn->prepare("INSERT INTO role_permissions (id_role, id_permission) VALUES (?, ?)");
                foreach ($permissions_checked as $pid) {
                    $stmt_perm->bind_param("ii", $id, $pid);
                    $stmt_perm->execute();
                }
                header("Location: roles.php?success=update");
                exit;
            } else {
                $error = "Gagal memperbarui data.";
            }
        }
    }
}

/*==============================
HAPUS DATA
==============================*/
if ($aksi == "hapus") {
    $id = intval($_GET['id']);
    
    // Cek apakah role sedang digunakan oleh dosen/user
    $cek_user = $conn->query("SELECT COUNT(*) AS total FROM users WHERE id_role = $id")->fetch_assoc();
    if ($cek_user['total'] > 0) {
        header("Location: roles.php?error=used");
        exit;
    } else {
        $stmt = $conn->prepare("DELETE FROM roles WHERE id_role = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            // role_permissions will be deleted automatically due to ON DELETE CASCADE
            header("Location: roles.php?success=hapus");
            exit;
        } else {
            header("Location: roles.php?error=hapus");
            exit;
        }
    }
}

// Ambil pesan sukses/gagal dari URL redirect
if (isset($_GET['success'])) {
    if ($_GET['success'] == 'tambah') $success = "Role baru berhasil ditambahkan!";
    if ($_GET['success'] == 'update') $success = "Role berhasil diperbarui!";
    if ($_GET['success'] == 'hapus') $success = "Role berhasil dihapus!";
}
if (isset($_GET['error'])) {
    if ($_GET['error'] == 'used') $error = "Role tidak dapat dihapus karena sedang digunakan oleh dosen.";
    if ($_GET['error'] == 'hapus') $error = "Gagal menghapus role.";
}

// Mode Edit: Ambil data role yang akan diedit
$editData = null;
$editPermissions = [];
if ($aksi == 'edit' && isset($_GET['id'])) {
    $id_edit = intval($_GET['id']);
    $res = $conn->query("SELECT * FROM roles WHERE id_role = $id_edit");
    if ($res->num_rows > 0) {
        $editData = $res->fetch_assoc();
        $res_perm = $conn->query("SELECT id_permission FROM role_permissions WHERE id_role = $id_edit");
        while ($rp = $res_perm->fetch_assoc()) {
            $editPermissions[] = $rp['id_permission'];
        }
    }
}

// Ambil semua data role
$roles = $conn->query("
    SELECT r.*, GROUP_CONCAT(p.deskripsi SEPARATOR '<br>') as list_permissions 
    FROM roles r
    LEFT JOIN role_permissions rp ON r.id_role = rp.id_role
    LEFT JOIN permissions p ON rp.id_permission = p.id_permission
    GROUP BY r.id_role
    ORDER BY r.id_role ASC
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
                    <?= $editData ? 'Edit Role' : 'Tambah Role Baru' ?>
                </h2>
            </div>
            
            <form method="POST" action="roles.php">
                <?php if ($editData): ?>
                    <input type="hidden" name="id_role" value="<?= $editData['id_role'] ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <label class="form-label" for="nama_role">Nama Role</label>
                    <input 
                        type="text" 
                        class="form-control" 
                        id="nama_role" 
                        name="nama_role" 
                        placeholder="Contoh: Staff, Kaprodi, dll" 
                        value="<?= $editData ? htmlspecialchars($editData['nama_role']) : '' ?>" 
                        required
                    >
                </div>
                
                <div class="form-group">
                    <label class="form-label">Izin Akses (Permissions)</label>
                    <div style="display: flex; flex-direction: column; gap: 8px; padding: 10px; border: 1px solid #e2e8f0; border-radius: 8px; background: #f8fafc;">
                        <?php foreach($all_permissions as $p): ?>
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" name="permissions[]" value="<?= $p['id_permission'] ?>" 
                                    class="perm-checkbox"
                                    data-name="<?= $p['nama_permission'] ?>"
                                    <?= in_array($p['id_permission'], $editPermissions) ? 'checked' : '' ?>
                                    onchange="toggleJamOperasional()">
                                <span style="font-size: 14px;"><?= htmlspecialchars($p['deskripsi']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div id="jam_operasional_container" style="display: <?= in_array($perm_operational_id, $editPermissions) ? 'block' : 'none' ?>;">
                    <div class="form-group">
                        <label class="form-label" for="jam_mulai">Jam Mulai Operasional</label>
                        <input type="time" class="form-control" id="jam_mulai" name="jam_mulai" value="<?= $editData ? htmlspecialchars($editData['jam_mulai']) : '' ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="jam_akhir">Jam Berakhir Operasional</label>
                        <input type="time" class="form-control" id="jam_akhir" name="jam_akhir" value="<?= $editData ? htmlspecialchars($editData['jam_akhir']) : '' ?>">
                    </div>
                </div>
                
                <div class="form-actions">
                    <?php if ($editData): ?>
                        <a href="roles.php" class="btn btn-secondary">Batal</a>
                        <button type="submit" name="update" class="btn btn-primary">
                            <i class="fas fa-save"></i> Perbarui
                        </button>
                    <?php else: ?>
                        <button type="submit" name="simpan" class="btn btn-success">
                            <i class="fas fa-plus"></i> Simpan Role
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
                <i class="fas fa-list" style="color: var(--primary);"></i>
                Daftar Role Pengguna
            </h2>
        </div>
        
        <div class="table-responsive">
            <table class="modern-table">
                <thead>
                    <tr>
                        <th width="50" style="text-align: center;">No</th>
                        <th>Nama Role</th>
                        <th>Permissions</th>
                        <th>Jam Operasional</th>
                        <th width="100" style="text-align: center;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($roles && $roles->num_rows > 0): $no = 1; ?>
                        <?php while ($row = $roles->fetch_assoc()): ?>
                            <tr>
                                <td align="center"><?= $no++ ?></td>
                                <td><strong><?= htmlspecialchars($row['nama_role']) ?></strong></td>
                                <td style="font-size: 13px;">
                                    <?= $row['list_permissions'] ? $row['list_permissions'] : '<span style="color:var(--text-muted);">- Tidak ada izin -</span>' ?>
                                </td>
                                <td>
                                    <?php
                                    if ($row['jam_mulai']) {
                                        echo htmlspecialchars(substr($row['jam_mulai'], 0, 5)) . ' - ' . htmlspecialchars(substr($row['jam_akhir'], 0, 5));
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                                <td align="center">
                                    <div style="display: flex; gap: 8px; justify-content: center;">
                                        <a href="?aksi=edit&id=<?= $row['id_role'] ?>" class="btn btn-secondary btn-sm" title="Edit">
                                            <i class="fas fa-edit" style="color: #3b82f6;"></i>
                                        </a>
                                        <a href="?aksi=hapus&id=<?= $row['id_role'] ?>" class="btn btn-secondary btn-sm" title="Hapus" onclick="return confirm('Apakah Anda yakin ingin menghapus role \'<?= htmlspecialchars($row['nama_role']) ?>\'?')">
                                            <i class="fas fa-trash-alt" style="color: #ef4444;"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" align="center" style="color: var(--text-muted); padding: 20px;">
                                Belum ada data role.
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
<script>
function toggleJamOperasional() {
    var checkboxes = document.querySelectorAll('.perm-checkbox');
    var showJam = false;
    checkboxes.forEach(function(cb) {
        if (cb.checked && cb.getAttribute('data-name') === 'access_operational') {
            showJam = true;
        }
    });
    
    var container = document.getElementById('jam_operasional_container');
    if (showJam) {
        container.style.display = 'block';
    } else {
        container.style.display = 'none';
    }
}
</script>
