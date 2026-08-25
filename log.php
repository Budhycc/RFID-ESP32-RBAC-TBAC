<?php
include "session_check.php";
include "config.php";

// Ambil input filter
$search     = isset($_GET['search']) ? trim($_GET['search']) : '';
$status     = isset($_GET['status']) ? trim($_GET['status']) : '';
$id_ruangan = isset($_GET['id_ruangan']) ? intval($_GET['id_ruangan']) : 0;

// Query dasar dengan relasi lengkap
$sql = "
    SELECT l.*, COALESCE(k.uid, l.scanned_uid) AS uid, u.nama AS nama_dosen, u.nidn, r.nama_ruangan, d.nama_device 
    FROM akses_log l 
    LEFT JOIN kartu k ON l.id_kartu = k.id_kartu 
    LEFT JOIN users u ON l.id_user = u.id_user 
    LEFT JOIN ruangan r ON l.id_ruangan = r.id_ruangan 
    LEFT JOIN devices d ON l.id_device = d.id_device
    WHERE 1=1
";

// Susun parameter bind
$params = [];
$types = '';

if ($search !== '') {
    $sql .= " AND (u.nama LIKE ? OR k.uid LIKE ? OR l.scanned_uid LIKE ? OR u.nidn LIKE ? OR l.keterangan LIKE ?)";
    $likeSearch = "%$search%";
    $params[] = $likeSearch;
    $params[] = $likeSearch;
    $params[] = $likeSearch;
    $params[] = $likeSearch;
    $params[] = $likeSearch;
    $types .= 'sssss';
}

if ($status !== '') {
    $sql .= " AND l.status = ?";
    $params[] = $status;
    $types .= 's';
}

if ($id_ruangan > 0) {
    $sql .= " AND l.id_ruangan = ?";
    $params[] = $id_ruangan;
    $types .= 'i';
}

// Urutkan berdasarkan waktu akses terbaru, batasi 100 record terakhir demi performa
$sql .= " ORDER BY l.id_log DESC LIMIT 100";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$logs = $stmt->get_result();

// Ambil data ruangan untuk filter dropdown
$ruangans = $conn->query("SELECT * FROM ruangan ORDER BY nama_ruangan ASC");

include "include/header.php";
?>

<div class="panel">
    <div class="panel-header">
        <h2 class="panel-title">
            <i class="fas fa-filter" style="color: var(--primary);"></i>
            Filter Riwayat Akses
        </h2>
    </div>

    <!-- Form Pencarian & Filter -->
    <form method="GET" action="log.php">
        <div class="form-row">
            <div class="form-group">
                <label class="form-label" for="search">Cari Nama / UID / NIDN</label>
                <input 
                    type="text" 
                    class="form-control" 
                    id="search" 
                    name="search" 
                    placeholder="Ketik kata kunci..." 
                    value="<?= htmlspecialchars($search) ?>"
                >
            </div>

            <div class="form-group">
                <label class="form-label" for="status">Filter Status</label>
                <select class="form-control" id="status" name="status">
                    <option value="">-- Semua Status --</option>
                    <option value="ALLOW" <?= $status === 'ALLOW' ? 'selected' : '' ?>>ALLOW (Diizinkan)</option>
                    <option value="DENY" <?= $status === 'DENY' ? 'selected' : '' ?>>DENY (Ditolak)</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label" for="id_ruangan">Lokasi Ruangan</label>
                <select class="form-control" id="id_ruangan" name="id_ruangan">
                    <option value="">-- Semua Ruangan --</option>
                    <?php if ($ruangans && $ruangans->num_rows > 0): ?>
                        <?php while ($r = $ruangans->fetch_assoc()): ?>
                            <option value="<?= $r['id_ruangan'] ?>" <?= $id_ruangan === intval($r['id_ruangan']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($r['nama_ruangan']) ?>
                            </option>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </select>
            </div>
        </div>

        <div class="form-actions" style="margin-top: 10px;">
            <?php if ($search !== '' || $status !== '' || $id_ruangan > 0): ?>
                <a href="log.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Bersihkan
                </a>
            <?php endif; ?>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-search"></i> Cari Data
            </button>
        </div>
    </form>
</div>

<div class="panel">
    <div class="panel-header">
        <h2 class="panel-title">
            <i class="fas fa-history" style="color: var(--primary);"></i>
            Log Aktivitas (Menampilkan Maksimal 100 Riwayat Terbaru)
        </h2>
    </div>

    <div class="table-responsive">
        <table class="modern-table">
            <thead>
                <tr>
                    <th width="50" style="text-align: center;">No</th>
                    <th>Waktu Akses</th>
                    <th>UID Kartu</th>
                    <th>Nama Dosen (NIDN)</th>
                    <th>Ruangan</th>
                    <th>Reader Device</th>
                    <th>Status</th>
                    <th>Keterangan</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($logs && $logs->num_rows > 0): $no = 1; ?>
                    <?php while ($row = $logs->fetch_assoc()): ?>
                        <tr>
                            <td align="center"><?= $no++ ?></td>
                            <td>
                                <strong><?= date("d/m/Y", strtotime($row['waktu_akses'])) ?></strong><br>
                                <small style="color: var(--text-muted); font-weight: 500;">
                                    <?= date("H:i:s", strtotime($row['waktu_akses'])) ?> WIB
                                </small>
                            </td>
                             <td>
                                <?php if (empty($row['id_kartu'])): ?>
                                    <code style="font-weight: bold; background-color: #fef2f2; color: #dc2626; padding: 4px 6px; border-radius: 4px; border: 1px solid #fca5a5; font-size: 13px;">
                                        <?= htmlspecialchars($row['uid'] ?? 'N/A') ?>
                                    </code>
                                <?php else: ?>
                                    <code style="font-weight: bold; background-color: #f1f5f9; padding: 4px 6px; border-radius: 4px; border: 1px solid var(--border-color); font-size: 13px;">
                                        <?= htmlspecialchars($row['uid'] ?? 'N/A') ?>
                                    </code>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($row['nama_dosen']): ?>
                                    <strong><?= htmlspecialchars($row['nama_dosen']) ?></strong><br>
                                    <small style="color: var(--text-muted);">NIDN: <?= htmlspecialchars($row['nidn']) ?></small>
                                <?php else: ?>
                                    <span style="color: var(--danger); font-style: italic; font-weight: 500;">Kartu Tidak Dikenal</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($row['nama_ruangan']): ?>
                                    <span class="badge badge-info"><?= htmlspecialchars($row['nama_ruangan']) ?></span>
                                <?php else: ?>
                                    <span style="color: var(--text-muted); font-style: italic;">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($row['nama_device']): ?>
                                    <span style="font-size: 13px; font-weight: 500; color: #475569;"><?= htmlspecialchars($row['nama_device']) ?></span>
                                <?php else: ?>
                                    <small style="color: var(--text-muted); font-family: monospace;"><?= htmlspecialchars($row['id_device'] ?? 'N/A') ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($row['status'] === 'ALLOW'): ?>
                                    <span class="badge badge-success">ALLOW</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">DENY</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span style="font-size: 13px; color: #475569;"><?= htmlspecialchars($row['keterangan'] ?? '-') ?></span>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" align="center" style="color: var(--text-muted); padding: 35px;">
                            <i class="fas fa-info-circle" style="font-size: 24px; display: block; margin-bottom: 10px;"></i>
                            Tidak ada data log akses yang sesuai dengan filter.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
include "include/footer.php";
?>
