<?php
require "../global_ji.php";
ensure_auth("petugas");

$show_report = false;
$report_type = 'borrowing_summary';
$d_awal = date('Y-m-01'); // First day of current month
$d_akhir = date('Y-m-t'); // Last day of current month

$message = "";
$message_type = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["generate"])) {
    $report_type = $_POST["report_type"];
    $d_awal = $_POST["d_awal"];
    $d_akhir = $_POST["d_akhir"];
    $show_report = true;
    
    // Validate date range
    if (strtotime($d_akhir) < strtotime($d_awal)) {
        $message = "Tanggal akhir tidak boleh lebih awal dari tanggal awal";
        $message_type = "error";
        $show_report = false;
    }
}

// Function to execute reports
function get_report_data($type, $start, $end) {
    $data = [];
    
    switch($type) {
        case 'borrowing_summary':
            // Summary of all borrowings
            $data['items'] = db_select("
                SELECT 
                    p.id_pinjam_ji,
                    p.batch_pinjam_ji,
                    u_req.username_user_ji as peminjam,
                    u_res.username_user_ji as petugas,
                    a.nama_alat_ji,
                    k.nama_kategori_ji,
                    p.jumlah_pinjam_ji,
                    p.d_awal_pinjam_ji,
                    p.d_akhir_pinjam_ji,
                    p.d_kembali_pinjam_ji,
                    p.status_pinjam_ji,
                    DATEDIFF(COALESCE(p.d_kembali_pinjam_ji, CURDATE()), p.d_akhir_pinjam_ji) as hari_terlambat
                FROM pinjam_ji p
                JOIN user_ji u_req ON p.user_req_pinjam_ji = u_req.id_user_ji
                LEFT JOIN user_ji u_res ON p.user_res_pinjam_ji = u_res.id_user_ji
                JOIN alat_ji a ON p.alat_pinjam_ji = a.id_alat_ji
                LEFT JOIN kategori_ji k ON a.kategori_alat_ji = k.id_kategori_ji
                WHERE p.d_awal_pinjam_ji BETWEEN ? AND ?
                ORDER BY p.d_awal_pinjam_ji DESC, p.batch_pinjam_ji DESC
            ", "ss", $start, $end);
            
            // Get statistics
            $stats = db_select("
                SELECT 
                    COUNT(*) as total_requests,
                    SUM(CASE WHEN status_pinjam_ji = 'diajukan' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status_pinjam_ji = 'dipinjam' THEN 1 ELSE 0 END) as dipinjam,
                    SUM(CASE WHEN status_pinjam_ji = 'dikembalikan' THEN 1 ELSE 0 END) as dikembalikan,
                    SUM(CASE WHEN status_pinjam_ji = 'selesai' THEN 1 ELSE 0 END) as selesai,
                    SUM(CASE WHEN status_pinjam_ji = 'ditolak' THEN 1 ELSE 0 END) as ditolak,
                    SUM(CASE WHEN status_pinjam_ji = 'dibatalkan' THEN 1 ELSE 0 END) as dibatalkan,
                    SUM(CASE WHEN status_pinjam_ji IN ('dikembalikan','selesai') 
                         AND d_kembali_pinjam_ji > d_akhir_pinjam_ji THEN 1 ELSE 0 END) as terlambat
                FROM pinjam_ji
                WHERE d_awal_pinjam_ji BETWEEN ? AND ?
            ", "ss", $start, $end);
            $data['stats'] = mysqli_fetch_assoc($stats);
            break;
            
        case 'equipment_popular':
            // Most borrowed equipment
            $data['items'] = db_select("
                SELECT 
                    a.id_alat_ji,
                    a.nama_alat_ji,
                    k.nama_kategori_ji,
                    a.stok_alat_ji as stok_sekarang,
                    COUNT(p.id_pinjam_ji) as total_peminjaman,
                    SUM(p.jumlah_pinjam_ji) as total_unit_dipinjam,
                    SUM(CASE WHEN p.status_pinjam_ji = 'dipinjam' THEN p.jumlah_pinjam_ji ELSE 0 END) as sedang_dipinjam,
                    SUM(CASE WHEN p.status_pinjam_ji IN ('dikembalikan','selesai') 
                         AND p.d_kembali_pinjam_ji > p.d_akhir_pinjam_ji THEN 1 ELSE 0 END) as keterlambatan
                FROM alat_ji a
                LEFT JOIN kategori_ji k ON a.kategori_alat_ji = k.id_kategori_ji
                LEFT JOIN pinjam_ji p ON a.id_alat_ji = p.alat_pinjam_ji 
                    AND p.d_awal_pinjam_ji BETWEEN ? AND ?
                    AND p.status_pinjam_ji NOT IN ('ditolak', 'dibatalkan')
                WHERE a.is_active_ji = 1
                GROUP BY a.id_alat_ji
                HAVING total_peminjaman > 0
                ORDER BY total_unit_dipinjam DESC, total_peminjaman DESC
            ", "ss", $start, $end);
            break;
            
        case 'user_activity':
            // User borrowing activity
            $data['items'] = db_select("
                SELECT 
                    u.id_user_ji,
                    u.username_user_ji,
                    u.role_user_ji,
                    COUNT(DISTINCT p.batch_pinjam_ji) as total_batch,
                    COUNT(p.id_pinjam_ji) as total_items,
                    SUM(CASE WHEN p.status_pinjam_ji = 'diajukan' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN p.status_pinjam_ji = 'dipinjam' THEN 1 ELSE 0 END) as aktif,
                    SUM(CASE WHEN p.status_pinjam_ji = 'selesai' THEN 1 ELSE 0 END) as selesai,
                    SUM(CASE WHEN p.status_pinjam_ji = 'ditolak' THEN 1 ELSE 0 END) as ditolak,
                    SUM(CASE WHEN p.status_pinjam_ji IN ('dikembalikan','selesai') 
                         AND p.d_kembali_pinjam_ji > p.d_akhir_pinjam_ji THEN 1 ELSE 0 END) as terlambat
                FROM user_ji u
                LEFT JOIN pinjam_ji p ON u.id_user_ji = p.user_req_pinjam_ji
                    AND p.d_awal_pinjam_ji BETWEEN ? AND ?
                WHERE u.role_user_ji = 'peminjam'
                GROUP BY u.id_user_ji
                HAVING total_items > 0
                ORDER BY total_items DESC, terlambat DESC
            ", "ss", $start, $end);
            break;
            
        case 'petugas_performance':
            // Petugas approval performance
            $data['items'] = db_select("
                SELECT 
                    u.id_user_ji,
                    u.username_user_ji,
                    COUNT(p.id_pinjam_ji) as total_ditangani,
                    SUM(CASE WHEN p.status_pinjam_ji = 'dipinjam' THEN 1 ELSE 0 END) as disetujui,
                    SUM(CASE WHEN p.status_pinjam_ji = 'ditolak' THEN 1 ELSE 0 END) as ditolak,
                    COUNT(DISTINCT DATE(p.updated_at_ji)) as hari_aktif,
                    COUNT(DISTINCT p.batch_pinjam_ji) as batch_ditangani
                FROM user_ji u
                LEFT JOIN pinjam_ji p ON u.id_user_ji = p.user_res_pinjam_ji
                    AND p.updated_at_ji BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
                WHERE u.role_user_ji = 'petugas'
                GROUP BY u.id_user_ji
                HAVING total_ditangani > 0
                ORDER BY total_ditangani DESC
            ", "ss", $start, $end);
            break;
            
        case 'late_returns':
            // Late returns analysis
            $data['items'] = db_select("
                SELECT 
                    p.id_pinjam_ji,
                    p.batch_pinjam_ji,
                    u.username_user_ji as peminjam,
                    a.nama_alat_ji,
                    p.jumlah_pinjam_ji,
                    p.d_akhir_pinjam_ji,
                    p.d_kembali_pinjam_ji,
                    DATEDIFF(p.d_kembali_pinjam_ji, p.d_akhir_pinjam_ji) as hari_terlambat,
                    p.status_pinjam_ji
                FROM pinjam_ji p
                JOIN user_ji u ON p.user_req_pinjam_ji = u.id_user_ji
                JOIN alat_ji a ON p.alat_pinjam_ji = a.id_alat_ji
                WHERE p.d_awal_pinjam_ji BETWEEN ? AND ?
                    AND p.status_pinjam_ji IN ('dikembalikan', 'selesai')
                    AND p.d_kembali_pinjam_ji > p.d_akhir_pinjam_ji
                ORDER BY hari_terlambat DESC
            ", "ss", $start, $end);
            
            // Get late return statistics
            $stats = db_select("
                SELECT 
                    COUNT(DISTINCT user_req_pinjam_ji) as total_peminjam_terlambat,
                    COUNT(*) as total_keterlambatan,
                    AVG(DATEDIFF(d_kembali_pinjam_ji, d_akhir_pinjam_ji)) as rata_rata_hari,
                    MAX(DATEDIFF(d_kembali_pinjam_ji, d_akhir_pinjam_ji)) as terlama
                FROM pinjam_ji
                WHERE d_awal_pinjam_ji BETWEEN ? AND ?
                    AND status_pinjam_ji IN ('dikembalikan', 'selesai')
                    AND d_kembali_pinjam_ji > d_akhir_pinjam_ji
            ", "ss", $start, $end);
            $data['stats'] = mysqli_fetch_assoc($stats);
            break;
            
        case 'category_analysis':
            // Analysis by category
            $data['items'] = db_select("
                SELECT 
                    k.id_kategori_ji,
                    k.nama_kategori_ji,
                    COUNT(DISTINCT a.id_alat_ji) as total_alat,
                    SUM(a.stok_alat_ji) as total_stok,
                    COUNT(p.id_pinjam_ji) as total_peminjaman,
                    SUM(p.jumlah_pinjam_ji) as total_unit_dipinjam,
                    SUM(CASE WHEN p.status_pinjam_ji = 'dipinjam' THEN p.jumlah_pinjam_ji ELSE 0 END) as sedang_dipinjam,
                    ROUND(AVG(CASE WHEN p.status_pinjam_ji NOT IN ('ditolak','dibatalkan') 
                         THEN p.jumlah_pinjam_ji END), 2) as rata_rata_peminjaman
                FROM kategori_ji k
                LEFT JOIN alat_ji a ON k.id_kategori_ji = a.kategori_alat_ji AND a.is_active_ji = 1
                LEFT JOIN pinjam_ji p ON a.id_alat_ji = p.alat_pinjam_ji
                    AND p.d_awal_pinjam_ji BETWEEN ? AND ?
                GROUP BY k.id_kategori_ji
                ORDER BY total_peminjaman DESC
            ", "ss", $start, $end);
            break;
            
        case 'stock_movement':
            // Stock movement report
            $data['items'] = db_select("
                SELECT 
                    a.id_alat_ji,
                    a.nama_alat_ji,
                    k.nama_kategori_ji,
                    a.stok_alat_ji as stok_sekarang,
                    SUM(CASE WHEN p.status_pinjam_ji = 'dipinjam' THEN p.jumlah_pinjam_ji ELSE 0 END) as keluar,
                    SUM(CASE WHEN p.status_pinjam_ji = 'selesai' THEN p.jumlah_pinjam_ji ELSE 0 END) as masuk,
                    (a.stok_alat_ji + 
                     SUM(CASE WHEN p.status_pinjam_ji = 'dipinjam' THEN p.jumlah_pinjam_ji ELSE 0 END) -
                     SUM(CASE WHEN p.status_pinjam_ji = 'selesai' THEN p.jumlah_pinjam_ji ELSE 0 END)
                    ) as stok_awal_periode
                FROM alat_ji a
                LEFT JOIN kategori_ji k ON a.kategori_alat_ji = k.id_kategori_ji
                LEFT JOIN pinjam_ji p ON a.id_alat_ji = p.alat_pinjam_ji
                    AND p.created_at_ji BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
                WHERE a.is_active_ji = 1
                GROUP BY a.id_alat_ji
                ORDER BY keluar DESC, a.nama_alat_ji
            ", "ss", $start, $end);
            break;
    }
    
    return $data;
}

// Get report data if generating
if ($show_report && $message_type !== "error") {
    $report_data = get_report_data($report_type, $d_awal, $d_akhir);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Laporan Peminjaman - Advanced</title>
    <style>
        .report-selector {
            background: #0f0f0f;
            padding: 20px;
            border: 1px solid #333;
            margin-bottom: 20px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group label {
            margin-bottom: 5px;
            color: #aaa;
            font-size: 13px;
        }
        
        .report-types {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .report-type-option {
            background: #0a0a0a;
            border: 1px solid #333;
            padding: 15px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .report-type-option:hover {
            border-color: #4a9eff;
        }
        
        .report-type-option input[type="radio"] {
            margin-right: 10px;
        }
        
        .report-type-option input[type="radio"]:checked + label {
            color: #4a9eff;
        }
        
        .report-description {
            font-size: 11px;
            color: #666;
            margin-top: 5px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-box {
            background: #0f0f0f;
            border: 1px solid #333;
            padding: 15px;
            text-align: center;
        }
        
        .stat-value {
            font-size: 32px;
            font-weight: bold;
            color: #4a9eff;
        }
        
        .stat-label {
            font-size: 11px;
            color: #888;
            text-transform: uppercase;
            margin-top: 5px;
        }
        
        .report-header {
            background: #0f0f0f;
            border: 1px solid #4a9eff;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .report-title {
            font-size: 20px;
            color: #4a9eff;
            margin-bottom: 10px;
        }
        
        .report-meta {
            color: #888;
            font-size: 13px;
        }
        
        @media print {
            .no-print {
                display: none !important;
            }
            
            body {
                background: white;
                color: black;
            }
            
            table, .stat-box, .report-header {
                border-color: #000 !important;
            }
            
            th, td {
                color: #000 !important;
            }
        }
    </style>
</head>
<body>
    <a href="../" class="no-print">&larr; Kembali</a>

    <?php if ($message): ?>
        <div class="message <?= $message_type ?>" id="message">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <h2>Laporan Peminjaman Alat - Advanced</h2>

    <form method="POST" class="report-selector no-print">
        <h3>Pilih Jenis Laporan</h3>
        
        <div class="report-types">
            <div class="report-type-option">
                <input type="radio" name="report_type" value="borrowing_summary" id="r1" 
                       <?= $report_type === 'borrowing_summary' ? 'checked' : '' ?>>
                <label for="r1">
                    <strong>Ringkasan Peminjaman</strong>
                    <div class="report-description">Detail semua peminjaman dengan statistik lengkap</div>
                </label>
            </div>
            
            <div class="report-type-option">
                <input type="radio" name="report_type" value="equipment_popular" id="r2"
                       <?= $report_type === 'equipment_popular' ? 'checked' : '' ?>>
                <label for="r2">
                    <strong>Alat Terpopuler</strong>
                    <div class="report-description">Alat yang paling banyak dipinjam</div>
                </label>
            </div>
            
            <div class="report-type-option">
                <input type="radio" name="report_type" value="user_activity" id="r3"
                       <?= $report_type === 'user_activity' ? 'checked' : '' ?>>
                <label for="r3">
                    <strong>Aktivitas Peminjam</strong>
                    <div class="report-description">Statistik peminjaman per user</div>
                </label>
            </div>
            
            <div class="report-type-option">
                <input type="radio" name="report_type" value="petugas_performance" id="r4"
                       <?= $report_type === 'petugas_performance' ? 'checked' : '' ?>>
                <label for="r4">
                    <strong>Kinerja Petugas</strong>
                    <div class="report-description">Performa approval petugas</div>
                </label>
            </div>
            
            <div class="report-type-option">
                <input type="radio" name="report_type" value="late_returns" id="r5"
                       <?= $report_type === 'late_returns' ? 'checked' : '' ?>>
                <label for="r5">
                    <strong>Analisis Keterlambatan</strong>
                    <div class="report-description">Daftar pengembalian terlambat</div>
                </label>
            </div>
            
            <div class="report-type-option">
                <input type="radio" name="report_type" value="category_analysis" id="r6"
                       <?= $report_type === 'category_analysis' ? 'checked' : '' ?>>
                <label for="r6">
                    <strong>Analisis per Kategori</strong>
                    <div class="report-description">Statistik berdasarkan kategori alat</div>
                </label>
            </div>
            
            <div class="report-type-option">
                <input type="radio" name="report_type" value="stock_movement" id="r7"
                       <?= $report_type === 'stock_movement' ? 'checked' : '' ?>>
                <label for="r7">
                    <strong>Pergerakan Stok</strong>
                    <div class="report-description">Keluar masuk stok alat</div>
                </label>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="d_awal">Tanggal Mulai:</label>
                <input type="date" id="d_awal" name="d_awal" value="<?= htmlspecialchars($d_awal) ?>" required>
            </div>

            <div class="form-group">
                <label for="d_akhir">Tanggal Akhir:</label>
                <input type="date" id="d_akhir" name="d_akhir" value="<?= htmlspecialchars($d_akhir) ?>" required>
            </div>
        </div>

        <input type="submit" name="generate" value="Generate Laporan">
    </form>

    <?php if ($show_report && $message_type !== "error"): ?>
        <div class="report-header">
            <div class="report-title">
                <?php
                $titles = [
                    'borrowing_summary' => 'Ringkasan Peminjaman',
                    'equipment_popular' => 'Alat Terpopuler',
                    'user_activity' => 'Aktivitas Peminjam',
                    'petugas_performance' => 'Kinerja Petugas',
                    'late_returns' => 'Analisis Keterlambatan',
                    'category_analysis' => 'Analisis per Kategori',
                    'stock_movement' => 'Pergerakan Stok'
                ];
                echo $titles[$report_type];
                ?>
            </div>
            <div class="report-meta">
                Periode: <?= date('d M Y', strtotime($d_awal)) ?> - <?= date('d M Y', strtotime($d_akhir)) ?><br>
                Digenerate: <?= date('d M Y H:i') ?> oleh <?= htmlspecialchars($_SESSION["username_user_ji"]) ?>
            </div>
        </div>

        <?php if ($report_type === 'borrowing_summary' && isset($report_data['stats'])): ?>
            <div class="stats-grid">
                <div class="stat-box">
                    <div class="stat-value"><?= $report_data['stats']['total_requests'] ?></div>
                    <div class="stat-label">Total Pengajuan</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value" style="color: #ffa500;"><?= $report_data['stats']['pending'] ?></div>
                    <div class="stat-label">Pending</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value" style="color: #4a9eff;"><?= $report_data['stats']['dipinjam'] ?></div>
                    <div class="stat-label">Dipinjam</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value" style="color: #4aff4a;"><?= $report_data['stats']['selesai'] ?></div>
                    <div class="stat-label">Selesai</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value" style="color: #ff4444;"><?= $report_data['stats']['ditolak'] ?></div>
                    <div class="stat-label">Ditolak</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value" style="color: #ff4444;"><?= $report_data['stats']['terlambat'] ?></div>
                    <div class="stat-label">Terlambat</div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($report_type === 'late_returns' && isset($report_data['stats'])): ?>
            <div class="stats-grid">
                <div class="stat-box">
                    <div class="stat-value"><?= $report_data['stats']['total_keterlambatan'] ?></div>
                    <div class="stat-label">Total Keterlambatan</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value"><?= $report_data['stats']['total_peminjam_terlambat'] ?></div>
                    <div class="stat-label">Peminjam Terlambat</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value"><?= number_format($report_data['stats']['rata_rata_hari'], 1) ?></div>
                    <div class="stat-label">Rata-rata Hari</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value"><?= $report_data['stats']['terlama'] ?></div>
                    <div class="stat-label">Terlama (hari)</div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($report_type === 'borrowing_summary'): ?>
            <table>
                <tr>
                    <th>Batch</th>
                    <th>Peminjam</th>
                    <th>Alat</th>
                    <th>Kategori</th>
                    <th>Jumlah</th>
                    <th>Tgl Pinjam</th>
                    <th>Tgl Harus Kembali</th>
                    <th>Tgl Kembali</th>
                    <th>Status</th>
                    <th>Diproses Oleh</th>
                    <th>Keterlambatan</th>
                </tr>
                <?php 
                if (mysqli_num_rows($report_data['items']) == 0): ?>
                    <tr><td colspan="11">Tidak ada data peminjaman pada periode ini</td></tr>
                <?php endif;
                
                while ($row = mysqli_fetch_assoc($report_data['items'])): ?>
                <tr>
                    <td><?= $row['batch_pinjam_ji'] ? '#'.$row['batch_pinjam_ji'] : '-' ?></td>
                    <td><?= htmlspecialchars($row['peminjam']) ?></td>
                    <td><?= htmlspecialchars($row['nama_alat_ji']) ?></td>
                    <td><?= htmlspecialchars($row['nama_kategori_ji']) ?></td>
                    <td><?= $row['jumlah_pinjam_ji'] ?></td>
                    <td><?= date('d/m/Y', strtotime($row['d_awal_pinjam_ji'])) ?></td>
                    <td><?= date('d/m/Y', strtotime($row['d_akhir_pinjam_ji'])) ?></td>
                    <td><?= $row['d_kembali_pinjam_ji'] ? date('d/m/Y', strtotime($row['d_kembali_pinjam_ji'])) : '-' ?></td>
                    <td><?= htmlspecialchars($row['status_pinjam_ji']) ?></td>
                    <td><?= htmlspecialchars($row['petugas'] ?: '-') ?></td>
                    <td>
                        <?php if ($row['hari_terlambat'] > 0): ?>
                            <strong style="color: #ff4444;"><?= $row['hari_terlambat'] ?> hari</strong>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </table>

        <?php elseif ($report_type === 'equipment_popular'): ?>
            <table>
                <tr>
                    <th>No</th>
                    <th>Nama Alat</th>
                    <th>Kategori</th>
                    <th>Stok Sekarang</th>
                    <th>Total Peminjaman</th>
                    <th>Total Unit Dipinjam</th>
                    <th>Sedang Dipinjam</th>
                    <th>Keterlambatan</th>
                </tr>
                <?php 
                if (mysqli_num_rows($report_data['items']) == 0): ?>
                    <tr><td colspan="8">Tidak ada data peminjaman alat pada periode ini</td></tr>
                <?php endif;
                
                $no = 1;
                while ($row = mysqli_fetch_assoc($report_data['items'])): ?>
                <tr>
                    <td><?= $no++ ?></td>
                    <td><?= htmlspecialchars($row['nama_alat_ji']) ?></td>
                    <td><?= htmlspecialchars($row['nama_kategori_ji']) ?></td>
                    <td><?= $row['stok_sekarang'] ?></td>
                    <td><?= $row['total_peminjaman'] ?>x</td>
                    <td><?= $row['total_unit_dipinjam'] ?> unit</td>
                    <td><?= $row['sedang_dipinjam'] ?> unit</td>
                    <td><?= $row['keterlambatan'] ?>x</td>
                </tr>
                <?php endwhile; ?>
            </table>

        <?php elseif ($report_type === 'user_activity'): ?>
            <table>
                <tr>
                    <th>No</th>
                    <th>Username</th>
                    <th>Total Batch</th>
                    <th>Total Items</th>
                    <th>Pending</th>
                    <th>Aktif</th>
                    <th>Selesai</th>
                    <th>Ditolak</th>
                    <th>Terlambat</th>
                </tr>
                <?php 
                if (mysqli_num_rows($report_data['items']) == 0): ?>
                    <tr><td colspan="9">Tidak ada aktivitas peminjam pada periode ini</td></tr>
                <?php endif;
                
                $no = 1;
                while ($row = mysqli_fetch_assoc($report_data['items'])): ?>
                <tr>
                    <td><?= $no++ ?></td>
                    <td><?= htmlspecialchars($row['username_user_ji']) ?></td>
                    <td><?= $row['total_batch'] ?></td>
                    <td><?= $row['total_items'] ?></td>
                    <td><?= $row['pending'] ?></td>
                    <td><?= $row['aktif'] ?></td>
                    <td><?= $row['selesai'] ?></td>
                    <td><?= $row['ditolak'] ?></td>
                    <td><?= $row['terlambat'] > 0 ? '<strong style="color: #ff4444;">'.$row['terlambat'].'</strong>' : '0' ?></td>
                </tr>
                <?php endwhile; ?>
            </table>

        <?php elseif ($report_type === 'petugas_performance'): ?>
            <table>
                <tr>
                    <th>No</th>
                    <th>Username Petugas</th>
                    <th>Total Ditangani</th>
                    <th>Disetujui</th>
                    <th>Ditolak</th>
                    <th>Batch Ditangani</th>
                    <th>Hari Aktif</th>
                    <th>Rata-rata/Hari</th>
                </tr>
                <?php 
                if (mysqli_num_rows($report_data['items']) == 0): ?>
                    <tr><td colspan="8">Tidak ada aktivitas petugas pada periode ini</td></tr>
                <?php endif;
                
                $no = 1;
                while ($row = mysqli_fetch_assoc($report_data['items'])): ?>
                <tr>
                    <td><?= $no++ ?></td>
                    <td><?= htmlspecialchars($row['username_user_ji']) ?></td>
                    <td><?= $row['total_ditangani'] ?></td>
                    <td><?= $row['disetujui'] ?></td>
                    <td><?= $row['ditolak'] ?></td>
                    <td><?= $row['batch_ditangani'] ?></td>
                    <td><?= $row['hari_aktif'] ?></td>
                    <td><?= $row['hari_aktif'] > 0 ? number_format($row['total_ditangani'] / $row['hari_aktif'], 1) : '0' ?></td>
                </tr>
                <?php endwhile; ?>
            </table>

        <?php elseif ($report_type === 'late_returns'): ?>
            <table>
                <tr>
                    <th>No</th>
                    <th>Batch</th>
                    <th>Peminjam</th>
                    <th>Alat</th>
                    <th>Jumlah</th>
                    <th>Tgl Harus Kembali</th>
                    <th>Tgl Kembali</th>
                    <th>Keterlambatan</th>
                    <th>Status</th>
                </tr>
                <?php 
                if (mysqli_num_rows($report_data['items']) == 0): ?>
                    <tr><td colspan="9">Tidak ada keterlambatan pada periode ini</td></tr>
                <?php endif;
                
                $no = 1;
                while ($row = mysqli_fetch_assoc($report_data['items'])): ?>
                <tr>
                    <td><?= $no++ ?></td>
                    <td><?= $row['batch_pinjam_ji'] ? '#'.$row['batch_pinjam_ji'] : '-' ?></td>
                    <td><?= htmlspecialchars($row['peminjam']) ?></td>
                    <td><?= htmlspecialchars($row['nama_alat_ji']) ?></td>
                    <td><?= $row['jumlah_pinjam_ji'] ?></td>
                    <td><?= date('d/m/Y', strtotime($row['d_akhir_pinjam_ji'])) ?></td>
                    <td><?= date('d/m/Y', strtotime($row['d_kembali_pinjam_ji'])) ?></td>
                    <td><strong style="color: #ff4444;"><?= $row['hari_terlambat'] ?> hari</strong></td>
                    <td><?= htmlspecialchars($row['status_pinjam_ji']) ?></td>
                </tr>
                <?php endwhile; ?>
            </table>

        <?php elseif ($report_type === 'category_analysis'): ?>
            <table>
                <tr>
                    <th>No</th>
                    <th>Kategori</th>
                    <th>Total Alat</th>
                    <th>Total Stok</th>
                    <th>Total Peminjaman</th>
                    <th>Total Unit Dipinjam</th>
                    <th>Sedang Dipinjam</th>
                    <th>Rata-rata Peminjaman</th>
                </tr>
                <?php 
                if (mysqli_num_rows($report_data['items']) == 0): ?>
                    <tr><td colspan="8">Tidak ada data kategori</td></tr>
                <?php endif;
                
                $no = 1;
                while ($row = mysqli_fetch_assoc($report_data['items'])): ?>
                <tr>
                    <td><?= $no++ ?></td>
                    <td><?= htmlspecialchars($row['nama_kategori_ji']) ?></td>
                    <td><?= $row['total_alat'] ?></td>
                    <td><?= $row['total_stok'] ?> unit</td>
                    <td><?= $row['total_peminjaman'] ?>x</td>
                    <td><?= $row['total_unit_dipinjam'] ?> unit</td>
                    <td><?= $row['sedang_dipinjam'] ?> unit</td>
                    <td><?= $row['rata_rata_peminjaman'] ?: '0' ?></td>
                </tr>
                <?php endwhile; ?>
            </table>

        <?php elseif ($report_type === 'stock_movement'): ?>
            <table>
                <tr>
                    <th>No</th>
                    <th>Nama Alat</th>
                    <th>Kategori</th>
                    <th>Stok Awal Periode</th>
                    <th>Keluar</th>
                    <th>Masuk</th>
                    <th>Stok Akhir (Sekarang)</th>
                </tr>
                <?php 
                if (mysqli_num_rows($report_data['items']) == 0): ?>
                    <tr><td colspan="7">Tidak ada pergerakan stok pada periode ini</td></tr>
                <?php endif;
                
                $no = 1;
                while ($row = mysqli_fetch_assoc($report_data['items'])): ?>
                <tr>
                    <td><?= $no++ ?></td>
                    <td><?= htmlspecialchars($row['nama_alat_ji']) ?></td>
                    <td><?= htmlspecialchars($row['nama_kategori_ji']) ?></td>
                    <td><?= $row['stok_awal_periode'] ?></td>
                    <td style="color: #ff4444;"><?= $row['keluar'] ? '-'.$row['keluar'] : '0' ?></td>
                    <td style="color: #4aff4a;"><?= $row['masuk'] ? '+'.$row['masuk'] : '0' ?></td>
                    <td><?= $row['stok_sekarang'] ?></td>
                </tr>
                <?php endwhile; ?>
            </table>
        <?php endif; ?>

        <button onclick="window.print()" class="no-print" style="margin-top: 20px;">🖨️ Cetak Laporan</button>
    <?php endif; ?>

    <script>
        const message = document.getElementById('message');
        if (message) {
            setTimeout(() => {
                message.style.opacity = '0';
                setTimeout(() => message.style.display = 'none', 500);
            }, 3000);
        }

        // Auto-set end date when start date changes
        document.getElementById('d_awal')?.addEventListener('change', function() {
            const startDate = new Date(this.value);
            const endInput = document.getElementById('d_akhir');
            
            // Set end date to one month after start date by default
            const endDate = new Date(startDate);
            endDate.setMonth(endDate.getMonth() + 1);
            
            // Format to YYYY-MM-DD
            const formattedDate = endDate.toISOString().split('T')[0];
            
            if (!endInput.value || new Date(endInput.value) < startDate) {
                endInput.value = formattedDate;
            }
            
            endInput.min = this.value;
        });
    </script>
</body>
</html>
