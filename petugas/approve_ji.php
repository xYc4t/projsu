<?php
require "../global_ji.php";
ensure_auth("petugas");

$message = "";
$message_type = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST["approve_batch"])) {
        $result = call_sp("sp_batch_approve_all_ji", [
            ['type' => 'i', 'value' => $_SESSION["id_user_ji"]],
            ['type' => 'i', 'value' => $_POST["batch_id"]]
        ], ['p_status_ji', 'p_message_ji']);

        if ($result['ok'] && isset($result['output'])) {
            $message = $result['output']['p_message_ji'];
            $message_type = $result['output']['p_status_ji'];
        }
    } elseif (isset($_POST["reject_batch"])) {
        $result = call_sp("sp_batch_decline_all_ji", [
            ['type' => 'i', 'value' => $_SESSION["id_user_ji"]],
            ['type' => 'i', 'value' => $_POST["batch_id"]]
        ]);

        if ($result["ok"]) {
            $message = "Batch berhasil ditolak";
            $message_type = "success";
        }
    } elseif (isset($_POST["approve_selected"])) {
        if (isset($_POST["selected_items"]) && is_array($_POST["selected_items"])) {
            $approved_count = 0;
            $failed_count = 0;
            
            foreach ($_POST["selected_items"] as $pinjam_id) {
                $result = call_sp("sp_pinjam_approve_ji", [
                    ['type' => 'i', 'value' => $_SESSION["id_user_ji"]],
                    ['type' => 'i', 'value' => $pinjam_id]
                ], ['p_status_ji', 'p_message_ji']);
                
                if ($result['ok'] && isset($result['output'])) {
                    if ($result['output']['p_status_ji'] === 'success') {
                        $approved_count++;
                    } else {
                        $failed_count++;
                    }
                } else {
                    $failed_count++;
                }
            }
            
            $message = "Disetujui: $approved_count item. Gagal: $failed_count item.";
            $message_type = $failed_count > 0 ? "warning" : "success";
        } else {
            $message = "Tidak ada item yang dipilih";
            $message_type = "error";
        }
    } elseif (isset($_POST["reject_selected"])) {
        if (isset($_POST["selected_items"]) && is_array($_POST["selected_items"])) {
            $rejected_count = 0;
            
            foreach ($_POST["selected_items"] as $pinjam_id) {
                $result = call_sp("sp_pinjam_decline_ji", [
                    ['type' => 'i', 'value' => $_SESSION["id_user_ji"]],
                    ['type' => 'i', 'value' => $pinjam_id]
                ]);
                
                if ($result['ok']) {
                    $rejected_count++;
                }
            }
            
            $message = "Berhasil menolak $rejected_count item";
            $message_type = "success";
        } else {
            $message = "Tidak ada item yang dipilih";
            $message_type = "error";
        }
    }
}

$search = isset($_GET['search']) ? $_GET['search'] : '';

// Get pending batches with their items
$sql = "SELECT b.*, u.username_user_ji,
        COUNT(p.id_pinjam_ji) as total_items
        FROM pinjam_batch_ji b
        JOIN user_ji u ON b.user_req_batch_ji = u.id_user_ji
        LEFT JOIN pinjam_ji p ON b.id_batch_ji = p.batch_pinjam_ji 
            AND p.status_pinjam_ji = 'diajukan'
        WHERE b.status_batch_ji = 'diajukan'";

if ($search) {
    $sql .= " AND u.username_user_ji LIKE ?";
    $sql .= " GROUP BY b.id_batch_ji HAVING COUNT(p.id_pinjam_ji) > 0 ORDER BY b.created_at_ji";
    $batches = db_select($sql, "s", "%$search%");
} else {
    $sql .= " GROUP BY b.id_batch_ji HAVING COUNT(p.id_pinjam_ji) > 0 ORDER BY b.created_at_ji";
    $batches = db_select($sql);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Setujui Peminjaman</title>
    <style>
        .batch-container {
            border: 1px solid #333;
            background: #0f0f0f;
            margin-bottom: 30px;
            padding: 15px;
        }
        
        .batch-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 10px;
            border-bottom: 1px solid #4a9eff;
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
        
        .batch-user {
            color: #fff;
            font-size: 14px;
        }
        
        .batch-date {
            color: #888;
            font-size: 12px;
        }
        
        .batch-actions-header {
            display: flex;
            gap: 10px;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        
        .items-table th {
            background: #1a1a1a;
            padding: 10px;
            text-align: left;
            font-size: 13px;
        }
        
        .items-table td {
            padding: 10px;
            border-bottom: 1px solid #222;
        }
        
        .items-table tr:hover {
            background: #151515;
        }
        
        .stock-warning {
            background: #2a1515;
            color: #ff4444;
        }
        
        .stock-ok {
            color: #4aff4a;
        }
        
        .selection-actions {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #333;
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .checkbox-cell {
            width: 40px;
            text-align: center;
        }
        
        input[type="checkbox"] {
            cursor: pointer;
        }
        
        .select-all-container {
            display: inline-block;
            margin-right: 15px;
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

    <h2>Pengajuan Peminjaman (Batch System)</h2>

    <form method="GET">
        <input type="text" name="search" placeholder="Cari username..." value="<?= htmlspecialchars($search) ?>">
        <input type="submit" value="Cari">
        <a href="approve_ji.php">Reset</a>
    </form>

    <?php if (mysqli_num_rows($batches) == 0): ?>
        <div class="empty-state">
            Tidak ada pengajuan peminjaman
        </div>
    <?php endif; ?>

    <?php while ($batch = mysqli_fetch_assoc($batches)): ?>
        <div class="batch-container">
            <div class="batch-header">
                <div class="batch-info">
                    <div class="batch-id">Batch #<?= $batch["id_batch_ji"] ?></div>
                    <div class="batch-user">Peminjam: <?= htmlspecialchars($batch["username_user_ji"]) ?></div>
                    <div class="batch-date">
                        Diajukan: <?= date('d M Y H:i', strtotime($batch["created_at_ji"])) ?> | 
                        Periode: <?= date('d M', strtotime($batch["d_awal_batch_ji"])) ?> - <?= date('d M Y', strtotime($batch["d_akhir_batch_ji"])) ?> |
                        Total: <?= $batch["total_items"] ?> item
                    </div>
                </div>
                <div class="batch-actions-header">
                    <form method="POST" style="display: inline; padding: 0; margin: 0; border: none;">
                        <input type="hidden" name="batch_id" value="<?= $batch["id_batch_ji"] ?>">
                        <input type="submit" name="approve_batch" value="✓ Setujui Semua"
                               onclick="return confirm('Setujui seluruh batch ini?')">
                    </form>
                    <form method="POST" style="display: inline; padding: 0; margin: 0; border: none;">
                        <input type="hidden" name="batch_id" value="<?= $batch["id_batch_ji"] ?>">
                        <input type="submit" name="reject_batch" value="✗ Tolak Semua"
                               onclick="return confirm('Tolak seluruh batch ini?')">
                    </form>
                </div>
            </div>
            
            <form method="POST" id="form-batch-<?= $batch["id_batch_ji"] ?>">
                <table class="items-table">
                    <thead>
                        <tr>
                            <th class="checkbox-cell">
                                <input type="checkbox" class="select-all" data-batch="<?= $batch["id_batch_ji"] ?>">
                            </th>
                            <th>No</th>
                            <th>Nama Alat</th>
                            <th>Jumlah Diminta</th>
                            <th>Stok Tersedia</th>
                            <th>Status Stok</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $items = db_select("
                            SELECT p.*, a.nama_alat_ji, a.stok_alat_ji
                            FROM pinjam_ji p
                            JOIN alat_ji a ON p.alat_pinjam_ji = a.id_alat_ji
                            WHERE p.batch_pinjam_ji = ? AND p.status_pinjam_ji = 'diajukan'
                            ORDER BY p.id_pinjam_ji
                        ", "i", $batch["id_batch_ji"]);
                        
                        $no = 1;
                        while ($item = mysqli_fetch_assoc($items)):
                            $stok_cukup = $item["stok_alat_ji"] >= $item["jumlah_pinjam_ji"];
                        ?>
                        <tr class="<?= !$stok_cukup ? 'stock-warning' : '' ?>">
                            <td class="checkbox-cell">
                                <input type="checkbox" name="selected_items[]" value="<?= $item["id_pinjam_ji"] ?>" 
                                       class="item-checkbox" data-batch="<?= $batch["id_batch_ji"] ?>">
                            </td>
                            <td><?= $no++ ?></td>
                            <td><?= htmlspecialchars($item["nama_alat_ji"]) ?></td>
                            <td><?= $item["jumlah_pinjam_ji"] ?></td>
                            <td><?= $item["stok_alat_ji"] ?></td>
                            <td>
                                <?php if ($stok_cukup): ?>
                                    <span class="stock-ok">✓ Stok Cukup</span>
                                <?php else: ?>
                                    <strong style="color: #ff4444;">✗ STOK TIDAK CUKUP!</strong>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                
                <div class="selection-actions">
                    <div class="select-all-container">
                        <label>
                            <input type="checkbox" class="select-all-footer" data-batch="<?= $batch["id_batch_ji"] ?>">
                            Pilih Semua
                        </label>
                    </div>
                    <input type="submit" name="approve_selected" value="Setujui yang Dipilih">
                    <input type="submit" name="reject_selected" value="Tolak yang Dipilih">
                    <span id="selected-count-<?= $batch["id_batch_ji"] ?>" style="color: #888; font-size: 13px;">
                        (0 item dipilih)
                    </span>
                </div>
            </form>
        </div>
    <?php endwhile; ?>

    <script>
        const message = document.getElementById('message');
        if (message) {
            setTimeout(() => {
                message.style.opacity = '0';
                setTimeout(() => message.style.display = 'none', 500);
            }, 3000);
        }

        // Select all functionality
        document.querySelectorAll('.select-all, .select-all-footer').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const batchId = this.dataset.batch;
                const checked = this.checked;
                
                // Update all checkboxes in this batch
                document.querySelectorAll(`.item-checkbox[data-batch="${batchId}"]`).forEach(item => {
                    item.checked = checked;
                });
                
                // Update both select-all checkboxes
                document.querySelectorAll(`.select-all[data-batch="${batchId}"], .select-all-footer[data-batch="${batchId}"]`).forEach(sa => {
                    sa.checked = checked;
                });
                
                updateSelectedCount(batchId);
            });
        });

        // Individual checkbox change
        document.querySelectorAll('.item-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const batchId = this.dataset.batch;
                updateSelectedCount(batchId);
                
                // Update select-all state
                const allCheckboxes = document.querySelectorAll(`.item-checkbox[data-batch="${batchId}"]`);
                const checkedCheckboxes = document.querySelectorAll(`.item-checkbox[data-batch="${batchId}"]:checked`);
                const allChecked = allCheckboxes.length === checkedCheckboxes.length;
                
                document.querySelectorAll(`.select-all[data-batch="${batchId}"], .select-all-footer[data-batch="${batchId}"]`).forEach(sa => {
                    sa.checked = allChecked;
                });
            });
        });

        function updateSelectedCount(batchId) {
            const count = document.querySelectorAll(`.item-checkbox[data-batch="${batchId}"]:checked`).length;
            const countElement = document.getElementById(`selected-count-${batchId}`);
            if (countElement) {
                countElement.textContent = `(${count} item dipilih)`;
            }
        }
        
        // Initialize counts
        document.querySelectorAll('[id^="selected-count-"]').forEach(element => {
            const batchId = element.id.replace('selected-count-', '');
            updateSelectedCount(batchId);
        });
    </script>
</body>
</html>
