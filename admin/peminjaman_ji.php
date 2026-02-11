<?php
require "../global_ji.php";
ensure_auth("admin");

$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$filter_user = isset($_GET['user']) ? $_GET['user'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

$sql = "SELECT p.*, u.username_user_ji, a.nama_alat_ji
        FROM pinjam_ji p
        JOIN user_ji u ON p.user_req_pinjam_ji = u.id_user_ji
        JOIN alat_ji a ON p.alat_pinjam_ji = a.id_alat_ji
        WHERE 1=1";
$params = [];
$types = "";

if ($filter_status) {
    $sql .= " AND p.status_pinjam_ji = ?";
    $types .= "s";
    $params[] = $filter_status;
}

if ($filter_user) {
    $sql .= " AND p.user_req_pinjam_ji = ?";
    $types .= "i";
    $params[] = $filter_user;
}

if ($search) {
    $sql .= " AND (u.username_user_ji LIKE ? OR a.nama_alat_ji LIKE ?)";
    $types .= "ss";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY p.id_pinjam_ji DESC";

$res = $types ? db_select($sql, $types, ...$params) : db_select($sql);
$user_list = db_select("SELECT id_user_ji, username_user_ji FROM user_ji WHERE role_user_ji = 'peminjam' ORDER BY username_user_ji");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Data Peminjaman</title>
</head>
<body>
    <a href="../">&larr; Kembali</a>

    <h2>Data Peminjaman</h2>

    <form method="GET">
        <input type="text" name="search" placeholder="Cari username/alat..." value="<?= htmlspecialchars($search) ?>">
        <select name="user">
            <option value="">Semua User</option>
            <?php while ($u = mysqli_fetch_assoc($user_list)): ?>
                <option value="<?= $u["id_user_ji"] ?>" <?= $filter_user == $u["id_user_ji"] ? "selected" : "" ?>>
                    <?= htmlspecialchars($u["username_user_ji"]) ?>
                </option>
            <?php endwhile; ?>
        </select>
        <select name="status">
            <option value="">Semua Status</option>
            <option value="diajukan" <?= $filter_status === 'diajukan' ? "selected" : "" ?>>Diajukan</option>
            <option value="dipinjam" <?= $filter_status === 'dipinjam' ? "selected" : "" ?>>Dipinjam</option>
            <option value="dikembalikan" <?= $filter_status === 'dikembalikan' ? "selected" : "" ?>>Dikembalikan</option>
            <option value="selesai" <?= $filter_status === 'selesai' ? "selected" : "" ?>>Selesai</option>
            <option value="ditolak" <?= $filter_status === 'ditolak' ? "selected" : "" ?>>Ditolak</option>
            <option value="dibatalkan" <?= $filter_status === 'dibatalkan' ? "selected" : "" ?>>Dibatalkan</option>
        </select>
        <input type="submit" value="Filter">
        <a href="peminjaman_ji.php">Reset</a>
    </form>

    <table border="1">
        <tr>
            <th>ID</th>
            <th>Username</th>
            <th>Nama Alat</th>
            <th>Jumlah</th>
            <th>Tgl Awal</th>
            <th>Tgl Akhir</th>
            <th>Tgl Kembali</th>
            <th>Status</th>
        </tr>
        <?php while ($row = mysqli_fetch_assoc($res)): ?>
        <tr>
            <th><?= htmlspecialchars($row["id_pinjam_ji"]) ?></th>
            <td><?= htmlspecialchars($row["username_user_ji"]) ?></td>
            <td><?= htmlspecialchars($row["nama_alat_ji"]) ?></td>
            <td><?= htmlspecialchars($row["jumlah_pinjam_ji"]) ?></td>
            <td><?= htmlspecialchars($row["d_awal_pinjam_ji"]) ?></td>
            <td><?= htmlspecialchars($row["d_akhir_pinjam_ji"]) ?></td>
            <td><?= htmlspecialchars($row["d_kembali_pinjam_ji"] ?: "-") ?></td>
            <td><?= htmlspecialchars($row["status_pinjam_ji"]) ?></td>
        </tr>
        <?php endwhile; ?>
    </table>
</body>
</html>
