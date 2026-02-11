<?php
require "../global_ji.php";
ensure_auth("peminjam");

$res = db_select("SELECT a.*, k.nama_kategori_ji
                  FROM alat_ji a
                  LEFT JOIN kategori_ji k ON a.kategori_alat_ji = k.id_kategori_ji
                  WHERE a.is_active_ji = 1
                  ORDER BY a.nama_alat_ji");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Daftar Alat</title>
</head>
<body>
    <a href="../">&larr; Kembali</a>

    <h2>Daftar Alat Tersedia</h2>

    <table border="1">
        <tr>
            <th>NO</th>
            <th>Nama Alat</th>
            <th>Kategori</th>
            <th>Stok Tersedia</th>
        </tr>
        <?php $i = 0; while ($row = mysqli_fetch_assoc($res)): $i++; ?>
        <tr>
            <th><?= htmlspecialchars($i) ?></th>
            <td><?= htmlspecialchars($row["nama_alat_ji"]) ?></td>
            <td><?= htmlspecialchars($row["nama_kategori_ji"]) ?></td>
            <td><?= htmlspecialchars($row["stok_alat_ji"]) ?></td>
        </tr>
        <?php endwhile; ?>
    </table>
</body>
</html>
