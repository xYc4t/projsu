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

// Get cart count for peminjam
$cart_count = 0;
if ($role === "peminjam") {
    $cart_result = db_select("SELECT COUNT(*) as count FROM cart_ji WHERE user_cart_ji = ?", "i", $_SESSION["id_user_ji"]);
    if ($cart_result && $row = mysqli_fetch_assoc($cart_result)) {
        $cart_count = $row['count'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home - Sistem Peminjaman Alat</title>
    <style>
        .cart-badge {
            position: relative;
            display: inline-block;
        }
        
        .cart-badge::after {
            content: '<?= $cart_count ?>';
            display: <?= $cart_count > 0 ? 'block' : 'none' ?>;
            position: absolute;
            top: -8px;
            right: -8px;
            background: #ff4444;
            color: #fff;
            border-radius: 10px;
            padding: 2px 6px;
            font-size: 11px;
            font-weight: bold;
            min-width: 18px;
            text-align: center;
        }
    </style>
</head>
<body>
    <h2>Sistem Peminjaman Alat</h2>
    
    <div style="background: #0f0f0f; padding: 15px; border: 1px solid #333; margin-bottom: 20px;">
        User: <strong><?= htmlspecialchars($_SESSION["username_user_ji"]) ?></strong><br>
        Role: <strong><?= htmlspecialchars($role) ?></strong><br>
        <a href="?logout=1">Logout</a>
    </div>

    <?php if ($role === "admin"): ?>
        <h3>Menu Admin</h3>
        <a href="admin/user_ji.php">Kelola User</a><br>
        <a href="admin/kategori_ji.php">Kelola Kategori</a><br>
        <a href="admin/alat_ji.php">Kelola Alat</a><br>
        <a href="admin/peminjaman_ji.php">Data Peminjaman</a><br>
        <a href="admin/log_ji.php">Log Aktifitas</a><br>
        
    <?php elseif ($role === "petugas"): ?>
        <h3>Menu Petugas</h3>
        <a href="petugas/approve_ji.php">Setujui Peminjaman (Batch System)</a><br>
        <a href="petugas/pengembalian_ji.php">Verifikasi Pengembalian</a><br>
        <a href="petugas/laporan_ji.php">Cetak Laporan</a>
        
    <?php elseif ($role === "peminjam"): ?>
        <h3>Menu Peminjam</h3>
        <a href="peminjam/daftar_alat_ji.php">📋 Katalog Alat</a><br>
        <a href="peminjam/cart_ji.php" class="cart-badge">
            🛒 Keranjang Peminjaman <?php if ($cart_count > 0): ?>(<?= $cart_count ?>)<?php endif; ?>
        </a><br>
        <a href="peminjam/peminjaman_ji.php">📊 Data Peminjaman Saya</a><br>
        <a href="peminjam/pengembalian_ji.php">↩️ Pengembalian Alat</a>
    <?php endif; ?>
</body>
</html>
