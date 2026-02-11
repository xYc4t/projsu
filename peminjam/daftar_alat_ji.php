<?php
require "../global_ji.php";
ensure_auth("peminjam");

$filter_kategori = isset($_GET['kategori']) ? $_GET['kategori'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

$sql = "SELECT a.*, k.nama_kategori_ji 
        FROM alat_ji a 
        LEFT JOIN kategori_ji k ON a.kategori_alat_ji = k.id_kategori_ji 
        WHERE a.is_active_ji = 1";

$params = [];
$types = "";

if ($filter_kategori) {
    $sql .= " AND a.kategori_alat_ji = ?";
    $types .= "i";
    $params[] = $filter_kategori;
}

if ($search) {
    $sql .= " AND a.nama_alat_ji LIKE ?";
    $types .= "s";
    $params[] = "%$search%";
}

$sql .= " ORDER BY a.nama_alat_ji";

$alat_list = $types ? db_select($sql, $types, ...$params) : db_select($sql);
$kategori_list = db_select("SELECT DISTINCT k.* 
                            FROM kategori_ji k 
                            INNER JOIN alat_ji a ON k.id_kategori_ji = a.kategori_alat_ji 
                            WHERE a.is_active_ji = 1 
                            ORDER BY k.nama_kategori_ji");

// Get cart count for badge
$cart_result = db_select("SELECT COUNT(*) as count FROM cart_ji WHERE user_cart_ji = ?", "i", $_SESSION["id_user_ji"]);
$cart_count = 0;
if ($cart_result && $row = mysqli_fetch_assoc($cart_result)) {
    $cart_count = $row['count'];
}

$message = "";
$message_type = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST["add_to_cart"])) {
        $result = call_sp("sp_cart_add_ji", [
            ['type' => 'i', 'value' => $_SESSION["id_user_ji"]],
            ['type' => 'i', 'value' => $_POST["alat"]],
            ['type' => 'i', 'value' => $_POST["jumlah"]]
        ], ['p_status_ji', 'p_message_ji']);

        if ($result['ok'] && isset($result['output'])) {
            $message = $result['output']['p_message_ji'];
            $message_type = $result['output']['p_status_ji'];
            
            // Refresh cart count
            if ($message_type === 'success') {
                $cart_result = db_select("SELECT COUNT(*) as count FROM cart_ji WHERE user_cart_ji = ?", "i", $_SESSION["id_user_ji"]);
                if ($cart_result && $row = mysqli_fetch_assoc($cart_result)) {
                    $cart_count = $row['count'];
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Alat</title>
    <style>
        .alat-image {
            width: 100%;
            height: 150px;
            object-fit: cover;
            border-radius: 8px 8px 0 0;
        }
        
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
        
        .header-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="header-nav">
        <a href="../">&larr; Kembali</a>
        <a href="cart_ji.php" class="cart-badge">
            🛒 Keranjang (<?= $cart_count ?>)
        </a>
    </div>

    <?php if ($message): ?>
        <div class="message <?= $message_type ?>" id="message">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <h2>Daftar Alat Tersedia</h2>

    <form method="GET">
        <input type="text" name="search" placeholder="Cari alat..." value="<?= htmlspecialchars($search) ?>">
        <select name="kategori">
            <option value="">Semua Kategori</option>
            <?php while ($k = mysqli_fetch_assoc($kategori_list)): ?>
                <option value="<?= $k["id_kategori_ji"] ?>" <?= $filter_kategori == $k["id_kategori_ji"] ? "selected" : "" ?>>
                    <?= htmlspecialchars($k["nama_kategori_ji"]) ?>
                </option>
            <?php endwhile; ?>
        </select>
        <input type="submit" value="Filter">
        <a href="daftar_alat_ji.php">Reset</a>
    </form>

    <div class="card-grid">
        <?php 
        mysqli_data_seek($alat_list, 0);
        while ($alat = mysqli_fetch_assoc($alat_list)): 
            $is_low_stock = $alat["stok_alat_ji"] < 3;
            $is_out_of_stock = $alat["stok_alat_ji"] == 0;
            
            $img_path = get_alat_image($alat["id_alat_ji"]);
        ?>
        <div class="alat-card">
            <img src="<?= htmlspecialchars($img_path) ?>?t=<?= time() ?>" class="alat-image" alt="<?= htmlspecialchars($alat["nama_alat_ji"]) ?>">
            
            <div class="card-header">
                <h3><?= htmlspecialchars($alat["nama_alat_ji"]) ?></h3>
                <?php if (!empty($alat["nama_kategori_ji"])): ?>
                    <span class="card-kategori"><?= htmlspecialchars($alat["nama_kategori_ji"]) ?></span>
                <?php endif; ?>
            </div>
            
            <div class="card-body">
                <div class="stock-info">
                    <span class="stock-label">Stok Tersedia:</span>
                    <span class="stock-value <?= $is_out_of_stock ? 'stock-empty' : ($is_low_stock ? 'stock-low' : 'stock-ok') ?>">
                        <?= htmlspecialchars($alat["stok_alat_ji"]) ?>
                    </span>
                </div>

                <?php if (!$is_out_of_stock): ?>
                <form method="POST" class="card-form">
                    <input type="hidden" name="alat" value="<?= $alat["id_alat_ji"] ?>">
                    
                    <div class="form-group-inline">
                        <label for="jumlah_<?= $alat["id_alat_ji"] ?>">Jumlah</label>
                        <input type="number" 
                               id="jumlah_<?= $alat["id_alat_ji"] ?>" 
                               name="jumlah" 
                               min="1" 
                               max="<?= $alat["stok_alat_ji"] ?>" 
                               value="1" 
                               required>
                    </div>

                    <input type="submit" name="add_to_cart" value="+ Tambah ke Keranjang" class="card-submit">
                </form>
                <?php else: ?>
                <div class="stock-empty-notice">
                    Stok habis
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endwhile; ?>
    </div>

    <?php if (mysqli_num_rows($alat_list) == 0): ?>
    <div class="empty-state">
        Tidak ada alat yang ditemukan
    </div>
    <?php endif; ?>

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
