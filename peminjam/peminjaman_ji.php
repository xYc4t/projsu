<?php
require "../global_ji.php";
ensure_auth("peminjam");

$message = "";
$message_type = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST["cancel_batch"])) {
        $result = call_sp("sp_batch_cancel_ji", [
            ['type' => 'i', 'value' => $_SESSION["id_user_ji"]],
            ['type' => 'i', 'value' => $_POST["batch_id"]]
        ]);

        if ($result["ok"]) {
            $message = "Batch peminjaman berhasil dibatalkan";
            $message_type = "success";
        } else {
            $message = $result["error"];
            $message_type = "error";
        }
    }
}

$filter_status = isset($_GET['status']) ? $_GET['status'] : '';

// Get batches
$sql_batch = "SELECT b.*, u.username_user_ji,
              COUNT(p.id_pinjam_ji) as total_items,
              SUM(CASE WHEN p.status_pinjam_ji = 'dipinjam' THEN 1 ELSE 0 END) as items_dipinjam,
              SUM(CASE WHEN p.status_pinjam_ji = 'diajukan' THEN 1 ELSE 0 END) as items_pending,
              SUM(CASE WHEN p.status_pinjam_ji = 'ditolak' THEN 1 ELSE 0 END) as items_ditolak,
              SUM(CASE WHEN p.status_pinjam_ji = 'selesai' THEN 1 ELSE 0 END) as items_selesai
              FROM pinjam_batch_ji b
              JOIN user_ji u ON b.user_req_batch_ji = u.id_user_ji
              LEFT JOIN pinjam_ji p ON b.id_batch_ji = p.batch_pinjam_ji
              WHERE b.user_req_batch_ji = ?";

$params = [$_SESSION["id_user_ji"]];
$types = "i";

if ($filter_status) {
    $sql_batch .= " AND b.status_batch_ji = ?";
    $types .= "s";
    $params[] = $filter_status;
}

$sql_batch .= " GROUP BY b.id_batch_ji ORDER BY b.created_at_ji DESC";

$batches = db_select($sql_batch, $types, ...$params);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Data Peminjaman</title>
    <style>
        .batch-container {
            border: 1px solid #333;
            background: #0f0f0f;
            margin-bottom: 20px;
            padding: 15px;
        }
        
        .batch-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 10px;
            border-bottom: 1px solid #222;
            margin-bottom: 15px;
        }
        
        .batch-info {
            flex: 1;
        }
        
        .batch-id {
            font-size: 18px;
            font-weight: bold;
            color: #4a9eff;
        }
        
        .batch-date {
            color: #888;
            font-size: 13px;
        }
        
        .batch-status {
            padding: 5px 12px;
            border: 1px solid;
            font-size: 13px;
            text-transform: uppercase;
        }
        
        .batch-status.diajukan {
            border-color: #ffa500;
            color: #ffa500;
        }
        
        .batch-status.disetujui {
            border-color: #4aff4a;
            color: #4aff4a;
        }
        
        .batch-status.ditolak {
            border-color: #ff4444;
            color: #ff4444;
        }
        
        .batch-status.dibatalkan {
            border-color: #666;
            color: #666;
        }
        
        .batch-items {
            margin-top: 10px;
        }
        
        .batch-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 10px;
            margin-bottom: 15px;
            padding: 10px;
            background: #0a0a0a;
            border: 1px solid #222;
        }
        
        .batch-summary-item {
            text-align: center;
        }
        
        .batch-summary-label {
            font-size: 11px;
            color: #666;
            text-transform: uppercase;
        }
        
        .batch-summary-value {
            font-size: 20px;
            font-weight: bold;
            color: #fff;
        }
        
        .item-list {
            border-collapse: collapse;
            width: 100%;
            font-size: 13px;
        }
        
        .item-list th {
            background: #1a1a1a;
            padding: 8px;
            font-size: 12px;
        }
        
        .item-list td {
            padding: 8px;
        }
        
        .batch-actions {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #222;
        }
    </style>
</head>
<body>
    <a href="../">&larr; Kembali</a>

    <?php if ($message): ?>
        <div class="message <?= $message_type ?>" id="message">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <h2>Data Peminjaman Saya</h2>

    <form method="GET">
        <select name="status">
            <option value="">Semua Status Batch</option>
            <option value="diajukan" <?= $filter_status === 'diajukan' ? "selected" : "" ?>>Diajukan</option>
            <option value="disetujui" <?= $filter_status === 'disetujui' ? "selected" : "" ?>>Disetujui</option>
            <option value="ditolak" <?= $filter_status === 'ditolak' ? "selected" : "" ?>>Ditolak</option>
            <option value="dibatalkan" <?= $filter_status === 'dibatalkan' ? "selected" : "" ?>>Dibatalkan</option>
        </select>
        <input type="submit" value="Filter">
        <a href="peminjaman_ji.php">Reset</a>
    </form>

    <?php if (mysqli_num_rows($batches) == 0): ?>
        <div class="empty-state">
            Belum ada riwayat peminjaman
        </div>
    <?php endif; ?>

    <?php while ($batch = mysqli_fetch_assoc($batches)): ?>
        <div class="batch-container">
            <div class="batch-header">
                <div class="batch-info">
                    <div class="batch-id">Batch #<?= $batch["id_batch_ji"] ?></div>
                    <div class="batch-date">
                        Diajukan: <?= date('d M Y H:i', strtotime($batch["created_at_ji"])) ?> | 
                        Periode: <?= date('d M', strtotime($batch["d_awal_batch_ji"])) ?> - <?= date('d M Y', strtotime($batch["d_akhir_batch_ji"])) ?>
                    </div>
                </div>
                <div class="batch-status <?= $batch["status_batch_ji"] ?>">
                    <?= strtoupper($batch["status_batch_ji"]) ?>
                </div>
            </div>
            
            <div class="batch-summary">
                <div class="batch-summary-item">
                    <div class="batch-summary-label">Total Item</div>
                    <div class="batch-summary-value"><?= $batch["total_items"] ?></div>
                </div>
                <?php if ($batch["items_pending"] > 0): ?>
                <div class="batch-summary-item">
                    <div class="batch-summary-label">Pending</div>
                    <div class="batch-summary-value" style="color: #ffa500;"><?= $batch["items_pending"] ?></div>
                </div>
                <?php endif; ?>
                <?php if ($batch["items_dipinjam"] > 0): ?>
                <div class="batch-summary-item">
                    <div class="batch-summary-label">Dipinjam</div>
                    <div class="batch-summary-value" style="color: #4a9eff;"><?= $batch["items_dipinjam"] ?></div>
                </div>
                <?php endif; ?>
                <?php if ($batch["items_ditolak"] > 0): ?>
                <div class="batch-summary-item">
                    <div class="batch-summary-label">Ditolak</div>
                    <div class="batch-summary-value" style="color: #ff4444;"><?= $batch["items_ditolak"] ?></div>
                </div>
                <?php endif; ?>
                <?php if ($batch["items_selesai"] > 0): ?>
                <div class="batch-summary-item">
                    <div class="batch-summary-label">Selesai</div>
                    <div class="batch-summary-value" style="color: #4aff4a;"><?= $batch["items_selesai"] ?></div>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="batch-items">
                <table class="item-list">
                    <tr>
                        <th>No</th>
                        <th>Nama Alat</th>
                        <th>Jumlah</th>
                        <th>Status Item</th>
                        <th>Tgl Kembali</th>
                    </tr>
                    <?php
                    $items = db_select("
                        SELECT p.*, a.nama_alat_ji
                        FROM pinjam_ji p
                        JOIN alat_ji a ON p.alat_pinjam_ji = a.id_alat_ji
                        WHERE p.batch_pinjam_ji = ?
                        ORDER BY p.id_pinjam_ji
                    ", "i", $batch["id_batch_ji"]);
                    
                    $no = 1;
                    while ($item = mysqli_fetch_assoc($items)):
                    ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td><?= htmlspecialchars($item["nama_alat_ji"]) ?></td>
                        <td><?= $item["jumlah_pinjam_ji"] ?></td>
                        <td><?= htmlspecialchars($item["status_pinjam_ji"]) ?></td>
                        <td><?= $item["d_kembali_pinjam_ji"] ? date('d M Y', strtotime($item["d_kembali_pinjam_ji"])) : '-' ?></td>
                    </tr>
                    <?php endwhile; ?>
                </table>
            </div>
            
            <?php if ($batch["status_batch_ji"] === "diajukan"): ?>
            <div class="batch-actions">
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="batch_id" value="<?= $batch["id_batch_ji"] ?>">
                    <input type="submit" name="cancel_batch" value="Batalkan Batch Ini"
                           onclick="return confirm('Yakin batalkan seluruh batch peminjaman ini?')">
                </form>
            </div>
            <?php endif; ?>
        </div>
    <?php endwhile; ?>
</body>
</html>
