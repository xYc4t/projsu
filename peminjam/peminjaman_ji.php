<?php
require "../global_ji.php";
ensure_auth("peminjam");

$message = "";
$message_type = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST["cancel"])) {
        $result = call_sp("sp_pinjam_cancel_ji", [
            ['type' => 'i', 'value' => $_SESSION["id_user_ji"]],
            ['type' => 'i', 'value' => $_POST["id"]]
        ]);

        if ($result["ok"]) {
            $message = "Pengajuan berhasil dibatalkan";
            $message_type = "success";
        } else {
            $message = $result["error"];
            $message_type = "error";
        }
    }
}

$filter_status = isset($_GET['status']) ? $_GET['status'] : '';

$sql = "SELECT p.*, a.nama_alat_ji
        FROM pinjam_ji p
        JOIN alat_ji a ON p.alat_pinjam_ji = a.id_alat_ji
        WHERE p.user_req_pinjam_ji = ?";

$params = [$_SESSION["id_user_ji"]];
$types = "i";

if ($filter_status) {
    $sql .= " AND p.status_pinjam_ji = ?";
    $types .= "s";
    $params[] = $filter_status;
}

$sql .= " ORDER BY p.id_pinjam_ji DESC";

$res = db_select($sql, $types, ...$params);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Data Peminjaman</title>
</head>
<body>
    <a href="../">&larr; Kembali</a>

    <?php if ($message): ?>
        <div class="message <?= $message_type ?>" id="message">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <h2>Data Peminjaman Saya</h2>

    <form method="GET">
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
            <th>NO</th>
            <th>Nama Alat</th>
            <th>Jumlah</th>
            <th>Tgl Mulai</th>
            <th>Tgl Akhir</th>
            <th>Tgl Kembali</th>
            <th>Status</th>
            <th>Aksi</th>
        </tr>
        <?php if (mysqli_num_rows($res) == 0): ?>
        <tr>
            <td colspan="8">Belum ada riwayat peminjaman</td>
        </tr>
        <?php endif; ?>
        <?php $i = 0; while ($row = mysqli_fetch_assoc($res)): $i++ ?>
        <tr>
            <form method="POST">
                <th><?= htmlspecialchars($i) ?></th>
                <td><?= htmlspecialchars($row["nama_alat_ji"]) ?></td>
                <td><?= htmlspecialchars($row["jumlah_pinjam_ji"]) ?></td>
                <td><?= htmlspecialchars($row["d_awal_pinjam_ji"]) ?></td>
                <td><?= htmlspecialchars($row["d_akhir_pinjam_ji"]) ?></td>
                <td><?= htmlspecialchars($row["d_kembali_pinjam_ji"] ?: "-") ?></td>
                <td><?= htmlspecialchars($row["status_pinjam_ji"]) ?></td>
                <td>
                    <?php if ($row["status_pinjam_ji"] === "diajukan"): ?>
                        <input type="hidden" name="id" value="<?= $row["id_pinjam_ji"] ?>">
                        <input type="submit" name="cancel" value="Batalkan"
                               onclick="return confirm('Yakin batalkan peminjaman ini?')">
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </td>
            </form>
        </tr>
        <?php endwhile; ?>
    </table>
</body>
</html>
