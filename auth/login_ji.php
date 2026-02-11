<?php
require "../global_ji.php";
ensure_guest();

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $result = db_select(
        "SELECT id_user_ji, username_user_ji, password_user_ji, role_user_ji, is_active_ji
         FROM user_ji
         WHERE username_user_ji = ?
         LIMIT 1",
        "s",
        $_POST["username"]
    );

    if ($result && mysqli_num_rows($result) === 1) {
        $user = mysqli_fetch_assoc($result);

        if (password_verify($_POST["password"], $user["password_user_ji"])) {
            if (!$user["is_active_ji"]) {
                $error = "Akun Anda telah dinonaktifkan. Hubungi administrator.";
            } else {
                $_SESSION["id_user_ji"]       = $user["id_user_ji"];
                $_SESSION["role_user_ji"]     = $user["role_user_ji"];
                $_SESSION["username_user_ji"] = $user["username_user_ji"];

                log_action($_SESSION["id_user_ji"], "Login ke sistem");

                header("Location: ../");
                exit;
            }
        } else {
            $error = "Login gagal. Username atau password salah!";
        }
    } else {
        $error = "Login gagal. Username atau password salah!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
</head>
<body>
    <h2>Login Sistem Peminjaman Alat</h2>

    <?php if ($error): ?>
        <p style="color: red;"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form method="POST">
        <input type="text" name="username" placeholder="username" required><br>
        <input type="password" name="password" placeholder="password" required><br>
        <input type="submit" value="Login">
    </form>
</body>
</html>
