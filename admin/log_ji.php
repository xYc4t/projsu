<?php
require "../global_ji.php";
ensure_auth("admin");

$res = db_select("SELECT l.*, u.username_user_ji
                  FROM log_ji l
                  JOIN user_ji u ON l.user_log_ji = u.id_user_ji
                  ORDER BY l.ts_log_ji DESC
                  LIMIT 100");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Log Aktifitas</title>
</head>
<body>
    <a href="../">&larr; Kembali</a>

    <h2>Log Aktivitas</h2>

    <table border="1">
        <tr>
            <th>ID</th>
            <th>User</th>
            <th>Aksi</th>
            <th>Waktu</th>
        </tr>
        <?php while ($row = mysqli_fetch_assoc($res)): ?>
        <tr>
            <th><?= htmlspecialchars($row["id_log_ji"]) ?></th>
            <td><?= htmlspecialchars($row["username_user_ji"]) ?></td>
            <td><?= htmlspecialchars($row["action_log_ji"]) ?></td>
            <td><?= htmlspecialchars($row["ts_log_ji"]) ?></td>
        </tr>
        <?php endwhile; ?>
    </table>
</body>
</html>
