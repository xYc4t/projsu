<?php
require "../global_ji.php";
ensure_auth("admin");

$message = "";
$message_type = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST["create"])) {
        $result = call_sp("sp_user_create_ji", [
            ['type' => 'i', 'value' => $_SESSION["id_user_ji"]],
            ['type' => 's', 'value' => $_POST["nusername"]],
            ['type' => 's', 'value' => password_hash($_POST["npassword"], PASSWORD_BCRYPT)],
            ['type' => 's', 'value' => $_POST["nrole"]]
        ], ['p_status_ji', 'p_message_ji']);

        if ($result['ok'] && isset($result['output'])) {
            $message = $result['output']['p_message_ji'];
            $message_type = $result['output']['p_status_ji'];
        }
    } elseif (isset($_POST["update"])) {
        $hashed_password = null;
        if (!empty($_POST["password"])) {
            $hashed_password = password_hash($_POST["password"], PASSWORD_BCRYPT);
        }

        $result = call_sp(
            "sp_user_update_ji",
            [
                ['type' => 'i', 'value' => $_SESSION["id_user_ji"]],
                ['type' => 'i', 'value' => $_POST["id"]],
                ['type' => 's', 'value' => $_POST["username"]],
                ['type' => 's', 'value' => $hashed_password],
                ['type' => 's', 'value' => $_POST["role"]],
            ],
            ['p_status_ji', 'p_message_ji']
        );

        if ($result['ok'] && isset($result['output'])) {
            $message      = $result['output']['p_message_ji'];
            $message_type = $result['output']['p_status_ji'];
        } else {
            $message = "Gagal mengupdate user";
            $message_type = "error";
        }
    } elseif (isset($_POST["disable"])) {
        $result = call_sp("sp_user_disable_ji", [
            ['type' => 'i', 'value' => $_SESSION["id_user_ji"]],
            ['type' => 'i', 'value' => $_POST["id"]]
        ], ['p_status_ji', 'p_message_ji']);

        if ($result['ok'] && isset($result['output'])) {
            $message = $result['output']['p_message_ji'];
            $message_type = $result['output']['p_status_ji'];
        }
    } elseif (isset($_POST["enable"])) {
        $result = call_sp("sp_user_enable_ji", [
            ['type' => 'i', 'value' => $_SESSION["id_user_ji"]],
            ['type' => 'i', 'value' => $_POST["id"]]
        ], ['p_status_ji', 'p_message_ji']);

        if ($result['ok'] && isset($result['output'])) {
            $message = $result['output']['p_message_ji'];
            $message_type = $result['output']['p_status_ji'];
        }
    }
}

$filter_role = isset($_GET['role']) ? $_GET['role'] : '';
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

$sql = "SELECT * FROM user_ji WHERE 1=1";
$params = [];
$types = "";

if ($filter_role) {
    $sql .= " AND role_user_ji = ?";
    $types .= "s";
    $params[] = $filter_role;
}

if ($filter_status !== '') {
    $sql .= " AND is_active_ji = ?";
    $types .= "i";
    $params[] = $filter_status;
}

if ($search) {
    $sql .= " AND username_user_ji LIKE ?";
    $types .= "s";
    $params[] = "%$search%";
}

$res = $types ? db_select($sql, $types, ...$params) : db_select($sql);
$roles = ["admin", "petugas", "peminjam"];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Kelola User</title>
</head>
<body>
    <a href="../">&larr; Kembali</a>

    <?php if ($message): ?>
        <div class="message <?= $message_type ?>" id="message">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <h2>Kelola User</h2>

    <form method="GET">
        <input type="text" name="search" placeholder="Cari username..." value="<?= htmlspecialchars($search) ?>">
        <select name="role">
            <option value="">Semua Role</option>
            <?php foreach ($roles as $role): ?>
                <option value="<?= $role ?>" <?= $filter_role === $role ? "selected" : "" ?>><?= $role ?></option>
            <?php endforeach; ?>
        </select>
        <select name="status">
            <option value="">Semua Status</option>
            <option value="1" <?= $filter_status === '1' ? "selected" : "" ?>>Aktif</option>
            <option value="0" <?= $filter_status === '0' ? "selected" : "" ?>>Nonaktif</option>
        </select>
        <input type="submit" value="Filter">
        <a href="user_ji.php">Reset</a>
    </form>

    <table border="1">
        <tr>
            <th>ID</th>
            <th>Username</th>
            <th>Password</th>
            <th>Role</th>
            <th>Status</th>
            <th>Aksi</th>
        </tr>
        <tr>
            <form method="POST">
                <th>Baru</th>
                <td><input type="text" name="nusername" required></td>
                <td><input type="password" name="npassword" required></td>
                <td>
                    <select name="nrole" required>
                        <option value="" hidden>-- Pilih Role --</option>
                        <?php foreach ($roles as $role): ?>
                            <option value="<?= $role ?>"><?= $role ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td>-</td>
                <td><input type="submit" name="create" value="Tambah"></td>
            </form>
        </tr>
        <?php while ($row = mysqli_fetch_assoc($res)): ?>
        <tr>
            <form method="POST">
                <th><?= htmlspecialchars($row["id_user_ji"]) ?></th>
                <td>
                    <input type="text" name="username" value="<?= htmlspecialchars($row["username_user_ji"]) ?>" required>
                </td>
                <td>
                    <input type="password" name="password" placeholder="(kosongkan jika tidak diubah)">
                </td>
                <td>
                    <select name="role" required>
                        <?php foreach ($roles as $role): ?>
                            <option value="<?= $role ?>" <?= $row["role_user_ji"] === $role ? "selected" : "" ?>>
                                <?= $role ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td>
                    <?= $row["is_active_ji"] ? "Aktif" : "Nonaktif" ?>
                </td>
                <td>
                    <input type="hidden" name="id" value="<?= $row["id_user_ji"] ?>">
                    <input type="submit" name="update" value="Simpan">
                    <?php if ($row["id_user_ji"] != $_SESSION["id_user_ji"]): ?>
                        <?php if ($row["is_active_ji"]): ?>
                            <input type="submit" name="disable" value="Nonaktifkan" onclick="return confirm('Yakin nonaktifkan user ini?')">
                        <?php else: ?>
                            <input type="submit" name="enable" value="Aktifkan" onclick="return confirm('Yakin aktifkan user ini?')">
                        <?php endif; ?>
                    <?php endif; ?>
                </td>
            </form>
        </tr>
        <?php endwhile; ?>
    </table>
</body>
</html>
