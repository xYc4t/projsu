<?php
require "../global_ji.php";
ensure_auth("peminjam");

$message = "";
$message_type = "";

// Handle cart actions
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
        } else {
            $message = "Gagal menambahkan ke keranjang";
            $message_type = "error";
        }
    } elseif (isset($_POST["update_cart"])) {
        $result = call_sp("sp_cart_update_ji", [
            ['type' => 'i', 'value' => $_POST["cart_id"]],
            ['type' => 'i', 'value' => $_POST["jumlah"]]
        ], ['p_status_ji', 'p_message_ji']);

        if ($result['ok'] && isset($result['output'])) {
            $message = $result['output']['p_message_ji'];
            $message_type = $result['output']['p_status_ji'];
        }
    } elseif (isset($_POST["remove_cart"])) {
        $result = call_sp("sp_cart_remove_ji", [
            ['type' => 'i', 'value' => $_POST["cart_id"]]
        ]);

        if ($result["ok"]) {
            $message = "Item dihapus dari keranjang";
            $message_type = "success";
        }
    } elseif (isset($_POST["clear_cart"])) {
        $result = call_sp("sp_cart_clear_ji", [
            ['type' => 'i', 'value' => $_SESSION["id_user_ji"]]
        ]);

        if ($result["ok"]) {
            $message = "Keranjang dikosongkan";
            $message_type = "success";
        }
    } elseif (isset($_POST["checkout"])) {
        $result = call_sp("sp_batch_create_ji", [
            ['type' => 'i', 'value' => $_SESSION["id_user_ji"]],
            ['type' => 's', 'value' => $_POST["d_awal"]],
            ['type' => 's', 'value' => $_POST["d_akhir"]]
        ], ['p_status_ji', 'p_message_ji', 'p_batch_id_ji']);

        if ($result['ok'] && isset($result['output'])) {
            $message = $result['output']['p_message_ji'];
            $message_type = $result['output']['p_status_ji'];
            
            if ($message_type === 'success') {
                header("Location: peminjaman_ji.php");
                exit;
            }
        }
    }
}

// Get cart items
$cart_items = db_select("
    SELECT c.*, a.nama_alat_ji, a.stok_alat_ji, k.nama_kategori_ji
    FROM cart_ji c
    JOIN alat_ji a ON c.alat_cart_ji = a.id_alat_ji
    LEFT JOIN kategori_ji k ON a.kategori_alat_ji = k.id_kategori_ji
    WHERE c.user_cart_ji = ?
    ORDER BY c.created_at_ji
", "i", $_SESSION["id_user_ji"]);

$cart_count = mysqli_num_rows($cart_items);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Keranjang Peminjaman</title>
    <style>
        .cart-container {
            max-width: 1000px;
            margin: 0 auto;
        }
        
        .cart-summary {
            background: #0f0f0f;
            border: 1px solid #333;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .cart-item {
            background: #0f0f0f;
            border: 1px solid #333;
            padding: 15px;
            margin-bottom: 10px;
            display: grid;
            grid-template-columns: 80px 1fr 150px 100px;
            gap: 15px;
            align-items: center;
        }
        
        .cart-item-image {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 4px;
            border: 1px solid #444;
        }
        
        .cart-item-info h4 {
            margin: 0 0 5px 0;
            color: #fff;
        }
        
        .cart-item-info h4::before {
            content: '';
        }
        
        .cart-item-category {
            color: #888;
            font-size: 12px;
        }
        
        .cart-item-stock {
            color: #666;
            font-size: 11px;
        }
        
        .cart-item-quantity {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .cart-item-quantity input {
            width: 60px;
            text-align: center;
        }
        
        .cart-item-actions {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .checkout-form {
            background: #0a0a0a;
            border: 1px solid #4a9eff;
            padding: 20px;
            margin-top: 20px;
        }
        
        .checkout-form h3 {
            margin-top: 0;
            color: #4a9eff;
        }
        
        .date-fields {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .empty-cart {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        
        .back-to-catalog {
            display: inline-block;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="cart-container">
        <a href="daftar_alat_ji.php" class="back-to-catalog">&larr; Lanjut Belanja</a>
        <a href="../" style="float: right;">&larr; Kembali ke Home</a>

        <?php if ($message): ?>
            <div class="message <?= $message_type ?>" id="message">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <h2>Keranjang Peminjaman</h2>

        <?php if ($cart_count > 0): ?>
            <div class="cart-summary">
                <strong>Total Item: <?= $cart_count ?></strong>
                <form method="POST" style="display: inline; float: right; padding: 0; margin: 0; border: none;">
                    <input type="submit" name="clear_cart" value="Kosongkan Keranjang" 
                           onclick="return confirm('Yakin hapus semua item dari keranjang?')"
                           style="border-color: #ff4444; color: #ff4444;">
                </form>
            </div>

            <?php mysqli_data_seek($cart_items, 0); ?>
            <?php while ($item = mysqli_fetch_assoc($cart_items)): 
                $img_path = get_alat_image($item["alat_cart_ji"]);
                $is_overstock = $item["jumlah_cart_ji"] > $item["stok_alat_ji"];
            ?>
            <div class="cart-item <?= $is_overstock ? 'border-error' : '' ?>">
                <img src="<?= htmlspecialchars($img_path) ?>?t=<?= time() ?>" class="cart-item-image" alt="<?= htmlspecialchars($item["nama_alat_ji"]) ?>">
                
                <div class="cart-item-info">
                    <h4><?= htmlspecialchars($item["nama_alat_ji"]) ?></h4>
                    <div class="cart-item-category"><?= htmlspecialchars($item["nama_kategori_ji"]) ?></div>
                    <div class="cart-item-stock">
                        Stok tersedia: <?= $item["stok_alat_ji"] ?>
                        <?php if ($is_overstock): ?>
                            <span style="color: #ff4444; font-weight: bold;"> (MELEBIHI STOK!)</span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <form method="POST" class="cart-item-quantity">
                    <input type="hidden" name="cart_id" value="<?= $item["id_cart_ji"] ?>">
                    <input type="number" name="jumlah" value="<?= $item["jumlah_cart_ji"] ?>" 
                           min="1" max="<?= $item["stok_alat_ji"] ?>" required>
                    <input type="submit" name="update_cart" value="Update">
                </form>
                
                <form method="POST" class="cart-item-actions">
                    <input type="hidden" name="cart_id" value="<?= $item["id_cart_ji"] ?>">
                    <input type="submit" name="remove_cart" value="Hapus"
                           onclick="return confirm('Hapus item ini dari keranjang?')">
                </form>
            </div>
            <?php endwhile; ?>

            <form method="POST" class="checkout-form">
                <h3>Checkout - Ajukan Semua Peminjaman</h3>
                
                <div class="date-fields">
                    <div>
                        <label for="d_awal">Tanggal Mulai Pinjam:</label><br>
                        <input type="date" id="d_awal" name="d_awal" min="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div>
                        <label for="d_akhir">Tanggal Harus Kembali:</label><br>
                        <input type="date" id="d_akhir" name="d_akhir" min="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>
                
                <input type="submit" name="checkout" value="Ajukan Semua Peminjaman (<?= $cart_count ?> item)" 
                       style="width: 100%; padding: 12px; font-size: 16px;">
            </form>

        <?php else: ?>
            <div class="empty-cart">
                <p>Keranjang Anda kosong</p>
                <a href="daftar_alat_ji.php">Mulai tambahkan alat ke keranjang</a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        const message = document.getElementById('message');
        if (message) {
            setTimeout(() => {
                message.style.opacity = '0';
                setTimeout(() => message.style.display = 'none', 500);
            }, 3000);
        }

        // Auto-update end date when start date changes
        document.getElementById('d_awal')?.addEventListener('change', function() {
            const endInput = document.getElementById('d_akhir');
            if (this.value) {
                const startDate = new Date(this.value);
                startDate.setDate(startDate.getDate() + 1);
                const minEndDate = startDate.toISOString().split('T')[0];
                endInput.min = minEndDate;
                if (!endInput.value || endInput.value < minEndDate) {
                    endInput.value = minEndDate;
                }
            }
        });
    </script>
</body>
</html>
