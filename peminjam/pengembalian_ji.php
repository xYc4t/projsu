<?php
require "../global_ji.php";
ensure_auth("peminjam");

$message = "";
$message_type = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST["return"])) {
        $result = call_sp("sp_pinjam_return_ji", [
            ['type' => 'i', 'value' => $_SESSION["id_user_ji"]],
            ['type' => 'i', 'value' => $_POST["id"]]
        ]);

        if ($result["ok"]) {
            $message = "Pengembalian telah dicatat, menunggu verifikasi petugas";
            $message_type = "success";
        } else {
            $message = $result["error"];
            $message_type = "error";
        }
    }
}

$res = db_select("SELECT p.*, a.nama_alat_ji
                  FROM pinjam_ji p
                  JOIN alat_ji a ON p.alat_pinjam_ji = a.id_alat_ji
                  WHERE p.user_req_pinjam_ji = ?
                  AND p.status_pinjam_ji IN ('dipinjam', 'dikembalikan')
                  ORDER BY p.d_akhir_pinjam_ji",
                  "i",
                  $_SESSION["id_user_ji"]);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Pengembalian Alat</title>
</head>
<body>
    <a href="../">&larr; Kembali</a>

    <?php if ($message): ?>
        <div class="message <?= $message_type ?>" id="message">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <h2>Pengembalian Alat Saya</h2>

    <table border="1">
        <tr>
            <th>NO</th>
            <th>Nama Alat</th>
            <th>Jumlah</th>
            <th>Tgl Pinjam</th>
            <th>Tgl Harus Kembali</th>
            <th>Status</th>
            <th>Aksi</th>
        </tr>
        <?php if (mysqli_num_rows($res) == 0): ?>
        <tr>
            <td colspan="7">Tidak ada alat yang sedang dipinjam</td>
        </tr>
        <?php endif; ?>
        <?php $i = 0; while ($row = mysqli_fetch_assoc($res)): $i++;
            $terlambat = strtotime($row["d_akhir_pinjam_ji"]) < strtotime(date('Y-m-d'));
            $sudah_dikembalikan = $row["status_pinjam_ji"] === 'dikembalikan';
        ?>
        <tr>
            <form method="POST">
                <th><?= htmlspecialchars($i) ?></th>
                <td><?= htmlspecialchars($row["nama_alat_ji"]) ?></td>
                <td><?= htmlspecialchars($row["jumlah_pinjam_ji"]) ?></td>
                <td><?= htmlspecialchars($row["d_awal_pinjam_ji"]) ?></td>
                <td><?= htmlspecialchars($row["d_akhir_pinjam_ji"]) ?></td>
                <td>
                    <?php if ($sudah_dikembalikan): ?>
                        Menunggu verifikasi petugas
                    <?php elseif ($terlambat): ?>
                        TERLAMBAT
                    <?php else: ?>
                        Normal
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (!$sudah_dikembalikan): ?>
                        <input type="hidden" name="id" value="<?= $row["id_pinjam_ji"] ?>">
                        <input type="submit" name="return" value="Kembalikan Alat"
                               onclick="return confirm('Konfirmasi pengembalian alat?')">
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
