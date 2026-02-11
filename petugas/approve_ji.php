<?php
require "../global_ji.php";
ensure_auth("petugas");

$message = "";
$message_type = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST["approve"])) {
        $result = call_sp("sp_pinjam_approve_ji", [
            ['type' => 'i', 'value' => $_SESSION["id_user_ji"]],
            ['type' => 'i', 'value' => $_POST["id"]]
        ], ['p_status_ji', 'p_message_ji']);

        if ($result['ok'] && isset($result['output'])) {
            $message = $result['output']['p_message_ji'];
            $message_type = $result['output']['p_status_ji'];
        } else {
            $message = $result["error"];
            $message_type = "error";
        }
    } elseif (isset($_POST["reject"])) {
        $result = call_sp("sp_pinjam_decline_ji", [
            ['type' => 'i', 'value' => $_SESSION["id_user_ji"]],
            ['type' => 'i', 'value' => $_POST["id"]],
        ]);

        if ($result["ok"]) {
            $message = "Peminjaman berhasil ditolak";
            $message_type = "success";
        } else {
            $message = $result["error"];
            $message_type = "error";
        }
    }
}

$search = isset($_GET['search']) ? $_GET['search'] : '';

$sql = "SELECT p.*, u.username_user_ji, a.nama_alat_ji, a.stok_alat_ji
        FROM pinjam_ji p
        JOIN user_ji u ON p.user_req_pinjam_ji = u.id_user_ji
        JOIN alat_ji a ON p.alat_pinjam_ji = a.id_alat_ji
        WHERE p.status_pinjam_ji = 'diajukan'";

if ($search) {
    $sql .= " AND (u.username_user_ji LIKE ? OR a.nama_alat_ji LIKE ?)";
    $sql .= " ORDER BY p.id_pinjam_ji";
    $res = db_select($sql, "ss", "%$search%", "%$search%");
} else {
    $sql .= " ORDER BY p.id_pinjam_ji";
    $res = db_select($sql);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Setujui Peminjaman</title>
</head>
<body>
    <a href="../">&larr; Kembali</a>

    <?php if ($message): ?>
        <div class="message <?= $message_type ?>" id="message">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <h2>Pengajuan Peminjaman</h2>

    <form method="GET">
        <input type="text" name="search" placeholder="Cari username/alat..." value="<?= htmlspecialchars($search) ?>">
        <input type="submit" value="Cari">
        <a href="approve_ji.php">Reset</a>
    </form>

    <table border="1">
        <tr>
            <th>NO</th>
            <th>Username</th>
            <th>Nama Alat</th>
            <th>Jumlah</th>
            <th>Stok Tersedia</th>
            <th>Tgl Awal</th>
            <th>Tgl Akhir</th>
            <th>Aksi</th>
        </tr>
        <?php if (mysqli_num_rows($res) == 0): ?>
        <tr>
            <td colspan="8">Tidak ada pengajuan peminjaman</td>
        </tr>
        <?php endif; ?>
        <?php $i = 0; while ($row = mysqli_fetch_assoc($res)): $i++; 
            $stok_cukup = $row["stok_alat_ji"] >= $row["jumlah_pinjam_ji"];
        ?>
        <tr style="<?= !$stok_cukup ? 'background-color: #ffcccc;' : '' ?>">
            <form method="POST">
                <th><?= htmlspecialchars($i) ?></th>
                <td><?= htmlspecialchars($row["username_user_ji"]) ?></td>
                <td><?= htmlspecialchars($row["nama_alat_ji"]) ?></td>
                <td><?= htmlspecialchars($row["jumlah_pinjam_ji"]) ?></td>
                <td>
                    <?= htmlspecialchars($row["stok_alat_ji"]) ?>
                    <?php if (!$stok_cukup): ?>
                        <strong style="color: red;">(TIDAK CUKUP!)</strong>
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($row["d_awal_pinjam_ji"]) ?></td>
                <td><?= htmlspecialchars($row["d_akhir_pinjam_ji"]) ?></td>
                <td>
                    <input type="hidden" name="id" value="<?= $row["id_pinjam_ji"] ?>">
                    <input type="submit" name="approve" value="Setujui"
                           onclick="return confirm('<?= $stok_cukup ? "Setujui peminjaman ini?" : "PERINGATAN: Stok tidak cukup! Tetap setujui?" ?>')">
                    <input type="submit" name="reject" value="Tolak"
                           onclick="return confirm('Tolak peminjaman ini?')">
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
