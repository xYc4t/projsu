<?php
require "../global_ji.php";
ensure_auth("admin");

$message = "";
$message_type = "";

// Simple image upload - saves as alat_{id}.{ext}
function upload_alat_image($file, $alat_id) {
    global $base;
    $upload_dir = $_SERVER['DOCUMENT_ROOT'] . $base . "/img/";
    
    // Create directory if not exists
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    // Check if file was uploaded
    if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }

    // Validate file type only
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $filename = $file['name'];
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    if (!in_array($ext, $allowed)) {
        return false;
    }

    // Delete old image with any extension (overwrite)
    $old_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    foreach ($old_extensions as $old_ext) {
        $old_file = $upload_dir . "alat_" . $alat_id . '.' . $old_ext;
        if (file_exists($old_file)) {
            unlink($old_file);
        }
    }

    // Save with new naming: alat_{id}.{ext}
    $new_filename = "alat_" . $alat_id . '.' . $ext;
    $filepath = $upload_dir . $new_filename;

    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return $new_filename;
    }

    return false;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST["create"])) {
        // Handle image upload first
        $image_filename = null;
        if (isset($_FILES["nimage"]) && $_FILES["nimage"]['error'] !== UPLOAD_ERR_NO_FILE) {
            // We'll get the ID after insert, so store the file temporarily
            $temp_file = $_FILES["nimage"];
        }
        
        $result = call_sp("sp_alat_create_ji", [
            ['type' => 'i', 'value' => $_SESSION["id_user_ji"]],
            ['type' => 's', 'value' => $_POST["nnama"]],
            ['type' => 'i', 'value' => $_POST["nkategori"]],
            ['type' => 'i', 'value' => $_POST["nstok"]],
            ['type' => 's', 'value' => null] // Image will be updated after we get ID
        ], ['p_status_ji', 'p_message_ji']);

        if ($result['ok'] && isset($result['output'])) {
            $message = $result['output']['p_message_ji'];
            $message_type = $result['output']['p_status_ji'];
            
            // If successful and image uploaded, save the image
            if ($message_type === 'success' && isset($temp_file)) {
                // Get the newly created alat ID
                $new_id_result = db_select("SELECT LAST_INSERT_ID() as id");
                if ($new_id_result && $row = mysqli_fetch_assoc($new_id_result)) {
                    $new_id = $row['id'];
                    $upload_result = upload_alat_image($temp_file, $new_id);
                    if ($upload_result === false) {
                        $message .= " (Gambar gagal diupload - format tidak didukung)";
                    } else if ($upload_result) {
                        // Update database with image filename
                        $conn = open();
                        $stmt = mysqli_prepare($conn, "UPDATE alat_ji SET image_alat_ji = ? WHERE id_alat_ji = ?");
                        mysqli_stmt_bind_param($stmt, "si", $upload_result, $new_id);
                        mysqli_stmt_execute($stmt);
                        mysqli_stmt_close($stmt);
                    }
                }
            }
        } else {
            $message = $result["error"];
            $message_type = "error";
        }
    } elseif (isset($_POST["update"])) {
        // Handle image upload if provided
        $image_filename = null;
        if (isset($_FILES["image"]) && $_FILES["image"]['error'] !== UPLOAD_ERR_NO_FILE) {
            $image_filename = upload_alat_image($_FILES["image"], $_POST["id"]);
            if ($image_filename === false) {
                $message = "Gambar gagal diupload - format tidak didukung";
                $message_type = "error";
                $image_filename = null;
            }
        }
        
        $result = call_sp("sp_alat_update_ji", [
            ['type' => 'i', 'value' => $_SESSION["id_user_ji"]],
            ['type' => 'i', 'value' => $_POST["id"]],
            ['type' => 's', 'value' => $_POST["nama"]],
            ['type' => 'i', 'value' => $_POST["kategori"]],
            ['type' => 'i', 'value' => $_POST["stok"]],
            ['type' => 's', 'value' => $image_filename]
        ]);

        if ($result["ok"]) {
            $message = "Alat berhasil diupdate";
            $message_type = "success";
        } else {
            $message = $result["error"];
            $message_type = "error";
        }
    } elseif (isset($_POST["disable"])) {
        $result = call_sp("sp_alat_disable_ji", [
            ['type' => 'i', 'value' => $_SESSION["id_user_ji"]],
            ['type' => 'i', 'value' => $_POST["id"]]
        ]);

        if ($result["ok"]) {
            $message = "Alat berhasil dinonaktifkan";
            $message_type = "success";
        } else {
            $message = $result["error"];
            $message_type = "error";
        }
    } elseif (isset($_POST["enable"])) {
        $result = call_sp("sp_alat_enable_ji", [
            ['type' => 'i', 'value' => $_SESSION["id_user_ji"]],
            ['type' => 'i', 'value' => $_POST["id"]]
        ]);

        if ($result["ok"]) {
            $message = "Alat berhasil diaktifkan";
            $message_type = "success";
        } else {
            $message = $result["error"];
            $message_type = "error";
        }
    }
}

$filter_kategori = isset($_GET['kategori']) ? $_GET['kategori'] : '';
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

$sql = "SELECT a.*, k.nama_kategori_ji
        FROM alat_ji a
        LEFT JOIN kategori_ji k ON a.kategori_alat_ji = k.id_kategori_ji
        WHERE 1=1";
$params = [];
$types = "";

if ($filter_kategori) {
    $sql .= " AND a.kategori_alat_ji = ?";
    $types .= "i";
    $params[] = $filter_kategori;
}

if ($filter_status !== '') {
    $sql .= " AND a.is_active_ji = ?";
    $types .= "i";
    $params[] = $filter_status;
}

if ($search) {
    $sql .= " AND a.nama_alat_ji LIKE ?";
    $types .= "s";
    $params[] = "%$search%";
}

$res = $types ? db_select($sql, $types, ...$params) : db_select($sql);
$kategori_list = db_select("SELECT * FROM kategori_ji ORDER BY nama_kategori_ji");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Kelola Alat</title>
    <style>
        table {
            width: 100%;
        }
        
        .alat-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 4px;
        }
        
        .image-preview {
            display: inline-block;
            margin-top: 5px;
        }
        
        .image-preview img {
            max-width: 100px;
            max-height: 100px;
            object-fit: cover;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .col-image {
            width: 120px;
            text-align: center;
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

    <h2>Kelola Alat</h2>

    <form method="GET">
        <input type="text" name="search" placeholder="Cari alat..." value="<?= htmlspecialchars($search) ?>">
        <select name="kategori">
            <option value="">Semua Kategori</option>
            <?php
            mysqli_data_seek($kategori_list, 0);
            while ($k = mysqli_fetch_assoc($kategori_list)):
            ?>
                <option value="<?= $k["id_kategori_ji"] ?>" <?= $filter_kategori == $k["id_kategori_ji"] ? "selected" : "" ?>>
                    <?= htmlspecialchars($k["nama_kategori_ji"]) ?>
                </option>
            <?php endwhile; ?>
        </select>
        <select name="status">
            <option value="">Semua Status</option>
            <option value="1" <?= $filter_status === '1' ? "selected" : "" ?>>Aktif</option>
            <option value="0" <?= $filter_status === '0' ? "selected" : "" ?>>Nonaktif</option>
        </select>
        <input type="submit" value="Filter">
        <a href="alat_ji.php">Reset</a>
    </form>

    <table border="1">
        <tr>
            <th>ID</th>
            <th class="col-image">Gambar</th>
            <th>Nama Alat</th>
            <th>Kategori</th>
            <th>Stok</th>
            <th>Status</th>
            <th>Aksi</th>
        </tr>
        <tr>
            <form method="POST" enctype="multipart/form-data">
                <th>Baru</th>
                <td class="col-image">
                    <input type="file" name="nimage" accept="image/*" onchange="previewImage(this, 'preview-new')">
                    <div id="preview-new" class="image-preview"></div>
                </td>
                <td><input type="text" name="nnama" required></td>
                <td>
                    <select name="nkategori" required>
                        <option value="" hidden>-- Pilih Kategori --</option>
                        <?php
                        mysqli_data_seek($kategori_list, 0);
                        while ($k = mysqli_fetch_assoc($kategori_list)):
                        ?>
                            <option value="<?= $k["id_kategori_ji"] ?>">
                                <?= htmlspecialchars($k["nama_kategori_ji"]) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </td>
                <td><input type="number" name="nstok" min="0" value="0" required></td>
                <td>-</td>
                <td><input type="submit" name="create" value="Tambah"></td>
            </form>
        </tr>
        <?php while ($row = mysqli_fetch_assoc($res)): ?>
        <tr>
            <form method="POST" enctype="multipart/form-data">
                <th><?= htmlspecialchars($row["id_alat_ji"]) ?></th>
                <td class="col-image">
                    <?php $img_path = get_alat_image($row["id_alat_ji"]); ?>
                    <img src="<?= htmlspecialchars($img_path) ?>?t=<?= time() ?>" class="alat-image" alt="<?= htmlspecialchars($row["nama_alat_ji"]) ?>">
                    <br>
                    <input type="file" name="image" accept="image/*" onchange="previewImage(this, 'preview-<?= $row["id_alat_ji"] ?>')">
                    <div id="preview-<?= $row["id_alat_ji"] ?>" class="image-preview"></div>
                </td>
                <td>
                    <input type="text" name="nama" value="<?= htmlspecialchars($row["nama_alat_ji"]) ?>" required>
                </td>
                <td>
                    <select name="kategori" required>
                        <?php
                        mysqli_data_seek($kategori_list, 0);
                        while ($k = mysqli_fetch_assoc($kategori_list)):
                        ?>
                            <option value="<?= $k["id_kategori_ji"] ?>"
                                <?= $row["kategori_alat_ji"] == $k["id_kategori_ji"] ? "selected" : "" ?>>
                                <?= htmlspecialchars($k["nama_kategori_ji"]) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </td>
                <td>
                    <input type="number" name="stok" value="<?= $row["stok_alat_ji"] ?>" min="0" required>
                </td>
                <td>
                    <?= $row["is_active_ji"] ? "Aktif" : "Nonaktif" ?>
                </td>
                <td>
                    <input type="hidden" name="id" value="<?= $row["id_alat_ji"] ?>">
                    <input type="submit" name="update" value="Simpan">
                    <?php if ($row["is_active_ji"]): ?>
                        <input type="submit" name="disable" value="Nonaktifkan" onclick="return confirm('Yakin nonaktifkan alat ini?')">
                    <?php else: ?>
                        <input type="submit" name="enable" value="Aktifkan" onclick="return confirm('Yakin aktifkan alat ini?')">
                    <?php endif; ?>
                </td>
            </form>
        </tr>
        <?php endwhile; ?>
    </table>

    <script>
        const message = document.getElementById('message');
        if (message) {
            setTimeout(() => {
                message.style.opacity = '0';
                setTimeout(() => message.style.display = 'none', 500);
            }, 3000);
        }

        function previewImage(input, previewId) {
            const preview = document.getElementById(previewId);
            preview.innerHTML = '';
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    preview.appendChild(img);
                };
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</body>
</html>
