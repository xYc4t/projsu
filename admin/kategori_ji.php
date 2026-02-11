<?php
require "../global_ji.php";
ensure_auth("admin");

$message = "";
$message_type = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST["create"])) {
        $result = call_sp("sp_kategori_create_ji", [
            ['type' => 'i', 'value' => $_SESSION["id_user_ji"]],
            ['type' => 's', 'value' => $_POST["nnama"]]
        ], ['p_status_ji', 'p_message_ji']);

        if ($result['ok'] && isset($result['output'])) {
            $message = $result['output']['p_message_ji'];
            $message_type = $result['output']['p_status_ji'];
        } else {
            $message = $result["error"];
            $message_type = "error";
        }
    } elseif (isset($_POST["update"])) {
        $result = call_sp("sp_kategori_update_ji", [
            ['type' => 'i', 'value' => $_SESSION["id_user_ji"]],
            ['type' => 'i', 'value' => $_POST["id"]],
            ['type' => 's', 'value' => $_POST["nama"]]
        ]);

        if ($result["ok"]) {
            $message = "Kategori berhasil diupdate";
            $message_type = "success";
        } else {
            $message = $result["error"];
            $message_type = "error";
        }
    } elseif (isset($_POST["delete"])) {
        $result = call_sp("sp_kategori_delete_ji", [
            ['type' => 'i', 'value' => $_SESSION["id_user_ji"]],
            ['type' => 'i', 'value' => $_POST["id"]]
        ], ['p_status_ji', 'p_message_ji']);

        if ($result['ok'] && isset($result['output'])) {
            $message = $result['output']['p_message_ji'];
            $message_type = $result['output']['p_status_ji'];
        }
    }
}

$search = isset($_GET['search']) ? $_GET['search'] : '';

if ($search) {
    $res = db_select("SELECT * FROM kategori_ji WHERE nama_kategori_ji LIKE ?", "s", "%$search%");
} else {
    $res = db_select("SELECT * FROM kategori_ji");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Kelola Kategori</title>
</head>
<body>
    <a href="../">&larr; Kembali</a>

    <?php if ($message): ?>
        <div class="message <?= $message_type ?>" id="message">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <h2>Kelola Kategori</h2>

    <form method="GET">
        <input type="text" name="search" placeholder="Cari kategori..." value="<?= htmlspecialchars($search) ?>">
        <input type="submit" value="Cari">
        <a href="kategori_ji.php">Reset</a>
    </form>

    <table border="1">
        <tr>
            <th>ID</th>
            <th>Nama Kategori</th>
            <th>Aksi</th>
        </tr>
        <tr>
            <form method="POST">
                <th>Baru</th>
                <td><input type="text" name="nnama" required></td>
                <td><input type="submit" name="create" value="Tambah"></td>
            </form>
        </tr>
        <?php while ($row = mysqli_fetch_assoc($res)): ?>
        <tr>
            <form method="POST">
                <th><?= htmlspecialchars($row["id_kategori_ji"]) ?></th>
                <td>
                    <input type="text" name="nama" value="<?= htmlspecialchars($row["nama_kategori_ji"]) ?>" required>
                </td>
                <td>
                    <input type="hidden" name="id" value="<?= $row["id_kategori_ji"] ?>">
                    <input type="submit" name="update" value="Simpan">
                    <input type="submit" name="delete" value="Hapus" onclick="return confirm('Yakin hapus kategori ini?')">
                </td>
            </form>
        </tr>
        <?php endwhile; ?>
    </table>
</body>
</html>
