<?php
require "global_ji.php";
ensure_auth();

if (isset($_GET["logout"])) {
    log_action($_SESSION["id_user_ji"], "Logout dari sistem");
    session_destroy();
    header("Location: auth/");
    exit();
}

$role = $_SESSION["role_user_ji"];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home</title>
</head>
<body>
    User: <?= htmlspecialchars($_SESSION["username_user_ji"]) ?><br>
    Role: <?= htmlspecialchars($role) ?><br>
    <a href="?logout=1">Logout</a><br><br>

    <?php if ($role === "admin"): ?>
        <a href="admin/user_ji.php">Kelola User</a><br>
        <a href="admin/kategori_ji.php">Kelola Kategori</a><br>
        <a href="admin/alat_ji.php">Kelola Alat</a><br>
        <a href="admin/peminjaman_ji.php">Data Peminjaman</a><br>
        <a href="admin/log_ji.php">Log Aktifitas</a><br>
    <?php elseif ($role === "petugas"): ?>
        <a href="petugas/approve_ji.php">Setujui Peminjaman</a><br>
        <a href="petugas/pengembalian_ji.php">Verifikasi Pengembalian</a><br>
        <a href="petugas/laporan_ji.php">Cetak Laporan</a>
    <?php elseif ($role === "peminjam"): ?>
        <a href="peminjam/daftar_alat_ji.php">Daftar Alat</a><br>
        <a href="peminjam/ajukan_pinjam_ji.php">Ajukan Peminjaman</a><br>
        <a href="peminjam/pengembalian_ji.php">Pengembalian Alat</a><br>
        <a href="peminjam/peminjaman_ji.php">Data Peminjaman</a>
    <?php endif; ?>
</body>
</html>
