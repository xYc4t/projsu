<?php
require "../global_ji.php";
ensure_auth("petugas");

$message = "";
$message_type = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST["verify"])) {
        $result = call_sp("sp_pinjam_finish_ji", [
            ['type' => 'i', 'value' => $_SESSION["id_user_ji"]],
            ['type' => 'i', 'value' => $_POST["id"]]
        ]);

        if ($result["ok"]) {
            $message = "Pengembalian berhasil diselesaikan";
            $message_type = "success";
        } else {
            $message = $result["error"];
            $message_type = "error";
        }
    }
}

$filter = isset($_GET['filter']) ? $_GET['filter'] : 'semua';
$search = isset($_GET['search']) ? $_GET['search'] : '';

$sql = "SELECT p.*, u.username_user_ji, a.nama_alat_ji
        FROM pinjam_ji p
        JOIN user_ji u ON p.user_req_pinjam_ji = u.id_user_ji
        JOIN alat_ji a ON p.alat_pinjam_ji = a.id_alat_ji
        WHERE p.status_pinjam_ji IN ('dipinjam', 'dikembalikan')";

if ($filter === 'pending') {
    $sql .= " AND p.status_pinjam_ji = 'dikembalikan'";
} elseif ($filter === 'terlambat') {
    $sql .= " AND p.status_pinjam_ji = 'dipinjam' AND p.d_akhir_pinjam_ji < CURDATE()";
} elseif ($filter === 'normal') {
    $sql .= " AND p.status_pinjam_ji = 'dipinjam' AND p.d_akhir_pinjam_ji >= CURDATE()";
}

if ($search) {
    $sql .= " AND (u.username_user_ji LIKE ? OR a.nama_alat_ji LIKE ?)";
    $sql .= " ORDER BY p.status_pinjam_ji DESC, p.d_kembali_pinjam_ji DESC, p.d_akhir_pinjam_ji";
    $res = db_select($sql, "ss", "%$search%", "%$search%");
} else {
    $sql .= " ORDER BY p.status_pinjam_ji DESC, p.d_kembali_pinjam_ji DESC, p.d_akhir_pinjam_ji";
    $res = db_select($sql);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Pantau Pengembalian</title>
</head>
<body>
    <a href="../">&larr; Kembali</a>

    <?php if ($message): ?>
        <div class="message <?= $message_type ?>" id="message">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <h2>Peminjaman Aktif & Pengembalian</h2>

    <form method="GET">
        <input type="text" name="search" placeholder="Cari username/alat..." value="<?= htmlspecialchars($search) ?>">
        <select name="filter">
            <option value="semua" <?= $filter === 'semua' ? "selected" : "" ?>>Semua</option>
            <option value="pending" <?= $filter === 'pending' ? "selected" : "" ?>>Menunggu Verifikasi</option>
            <option value="terlambat" <?= $filter === 'terlambat' ? "selected" : "" ?>>Terlambat</option>
            <option value="normal" <?= $filter === 'normal' ? "selected" : "" ?>>Normal</option>
        </select>
        <input type="submit" value="Filter">
        <a href="pengembalian_ji.php">Reset</a>
    </form>

    <table border="1">
        <tr>
            <th>NO</th>
            <th>Username</th>
            <th>Nama Alat</th>
            <th>Jumlah</th>
            <th>Tgl Pinjam</th>
            <th>Tgl Harus Kembali</th>
            <th>Tgl Dikembalikan</th>
            <th>Status</th>
            <th>Aksi</th>
        </tr>
        <?php if (mysqli_num_rows($res) == 0): ?>
        <tr>
            <td colspan="9">Tidak ada peminjaman aktif</td>
        </tr>
        <?php endif; ?>
        <?php $i = 0; while ($row = mysqli_fetch_assoc($res)): $i++;
            $terlambat = strtotime($row["d_akhir_pinjam_ji"]) < strtotime(date('Y-m-d'));
            $menunggu_verifikasi = $row["status_pinjam_ji"] === 'dikembalikan';
        ?>
        <tr>
            <form method="POST">
                <th><?= htmlspecialchars($i) ?></th>
                <td><?= htmlspecialchars($row["username_user_ji"]) ?></td>
                <td><?= htmlspecialchars($row["nama_alat_ji"]) ?></td>
                <td><?= htmlspecialchars($row["jumlah_pinjam_ji"]) ?></td>
                <td><?= htmlspecialchars($row["d_awal_pinjam_ji"]) ?></td>
                <td><?= htmlspecialchars($row["d_akhir_pinjam_ji"]) ?></td>
                <td><?= htmlspecialchars($row["d_kembali_pinjam_ji"] ?: "-") ?></td>
                <td>
                    <?php if ($menunggu_verifikasi): ?>
                        <strong>Menunggu Verifikasi</strong>
                    <?php elseif ($terlambat): ?>
                        TERLAMBAT
                    <?php else: ?>
                        Normal
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($menunggu_verifikasi): ?>
                        <input type="hidden" name="id" value="<?= $row["id_pinjam_ji"] ?>">
                        <input type="submit" name="verify" value="Selesaikan Peminjaman"
                               onclick="return confirm('Konfirmasi pengembalian alat sudah diterima?')">
                    <?php else: ?>
                        Belum dikembalikan
                    <?php endif; ?>
                </td>
            </form>
        </tr>
        <?php endwhile; ?>
    </table>

    <script>
        const message = document.getElementById('message');
        if (message) {
            setTimeout(() => {
                message.style.opacity = '0';
                setTimeout(() => message.style.display = 'none', 500);
            }, 3000);
        }
    </script>
</body>
</html>
