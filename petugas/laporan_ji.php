<?php
require "../global_ji.php";
ensure_auth("petugas");

$show_report = false;
$bulan = date('n');
$tahun = date('Y');

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $bulan = $_POST["bulan"];
    $tahun = $_POST["tahun"];
    $show_report = true;

    $conn = open();
    $stmt = mysqli_prepare($conn, "CALL sp_laporan_all_in_one_ji(?, ?)");
    mysqli_stmt_bind_param($stmt, "ii", $bulan, $tahun);
    mysqli_stmt_execute($stmt);

    $result1 = mysqli_stmt_get_result($stmt);
    $data_pinjam = [];
    while ($row = mysqli_fetch_assoc($result1)) {
        $data_pinjam[] = $row;
    }

    mysqli_stmt_next_result($stmt);
    $result2 = mysqli_stmt_get_result($stmt);
    $data_alat = [];
    while ($row = mysqli_fetch_assoc($result2)) {
        $data_alat[] = $row;
    }

    mysqli_stmt_next_result($stmt);
    $result3 = mysqli_stmt_get_result($stmt);
    $data_user = [];
    while ($row = mysqli_fetch_assoc($result3)) {
        $data_user[] = $row;
    }

    mysqli_stmt_close($stmt);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Cetak Laporan</title>
</head>
<body>
    <a href="../" class="no-print">&larr; Kembali</a>

    <h2>Laporan Peminjaman Alat</h2>

    <form method="POST" class="no-print">
        <label>Bulan:</label>
        <select name="bulan" required>
            <?php for ($i = 1; $i <= 12; $i++): ?>
                <option value="<?= $i ?>" <?= $i == $bulan ? "selected" : "" ?>>
                    <?= date('F', mktime(0, 0, 0, $i, 1)) ?>
                </option>
            <?php endfor; ?>
        </select>

        <label>Tahun:</label>
        <input type="number" name="tahun" value="<?= $tahun ?>" min="2020" max="2030" required>

        <input type="submit" value="Generate Laporan">
    </form>

    <?php if ($show_report): ?>
        <hr>
        <h3>Laporan Periode: <?= date('F', mktime(0, 0, 0, $bulan, 1)) ?> <?= $tahun ?></h3>

        <h4>1. Detail Peminjaman</h4>
        <table>
            <tr>
                <th>NO</th>
                <th>Username</th>
                <th>Nama Alat</th>
                <th>Jumlah</th>
                <th>Tgl Awal</th>
                <th>Tgl Akhir</th>
                <th>Tgl Kembali</th>
                <th>Status</th>
            </tr>
            <?php if (empty($data_pinjam)): ?>
            <tr>
                <td colspan="8">Tidak ada data peminjaman pada periode ini</td>
            </tr>
            <?php endif; ?>
            <?php $i = 0; foreach ($data_pinjam as $row): $i++; ?>
            <tr>
                <th><?= htmlspecialchars($i) ?></th>
                <td><?= htmlspecialchars($row["username_user_ji"]) ?></td>
                <td><?= htmlspecialchars($row["nama_alat_ji"]) ?></td>
                <td><?= htmlspecialchars($row["jumlah_pinjam_ji"]) ?></td>
                <td><?= htmlspecialchars($row["d_awal_pinjam_ji"]) ?></td>
                <td><?= htmlspecialchars($row["d_akhir_pinjam_ji"]) ?></td>
                <td><?= htmlspecialchars($row["d_kembali_pinjam_ji"] ?: "-") ?></td>
                <td><?= htmlspecialchars($row["status_pinjam_ji"]) ?></td>
            </tr>
            <?php endforeach; ?>
        </table>

        <h4>2. Alat Terpopuler</h4>
        <table>
            <tr>
                <th>NO</th>
                <th>Nama Alat</th>
                <th>Jumlah Pengajuan</th>
            </tr>
            <?php if (empty($data_alat)): ?>
            <tr>
                <td colspan="3">Tidak ada data alat pada periode ini</td>
            </tr>
            <?php endif; ?>
            <?php $i = 0; foreach ($data_alat as $row): $i++; ?>
            <tr>
                <th><?= htmlspecialchars($i) ?></th>
                <td><?= htmlspecialchars($row["nama_alat_ji"]) ?></td>
                <td><?= htmlspecialchars($row["total_dipinjam"]) ?></td>
            </tr>
            <?php endforeach; ?>
        </table>

        <h4>3. Peminjam Teraktif</h4>
        <table>
            <tr>
                <th>NO</th>
                <th>Username</th>
                <th>Total Peminjaman</th>
            </tr>
            <?php if (empty($data_user)): ?>
            <tr>
                <td colspan="3">Tidak ada data peminjam pada periode ini</td>
            </tr>
            <?php endif; ?>
            <?php $i = 0; foreach ($data_user as $row): $i++; ?>
            <tr>
                <th><?= htmlspecialchars($i) ?></th>
                <td><?= htmlspecialchars($row["username_user_ji"]) ?></td>
                <td><?= htmlspecialchars($row["total_pinjam"]) ?></td>
            </tr>
            <?php endforeach; ?>
        </table>

        <button onclick="window.print()" class="no-print">Cetak Laporan</button>
    <?php endif; ?>
</body>
</html>
