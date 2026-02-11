<?php
session_start();
mysqli_report(MYSQLI_REPORT_OFF);

$base = "/ye";

echo "<link rel='stylesheet' href='$base/style_ji.css'>";

function ensure_auth(?string $role = null)
{
    global $base;

    if (!isset($_SESSION['id_user_ji'])) {
        header("Location: $base/auth/");
        exit;
    }

    if ($role !== null && $_SESSION['role_user_ji'] !== $role) {
        header("Location: $base/");
        exit;
    }
}

function ensure_guest() {
    global $base;

    if (isset($_SESSION['id_user_ji'])) {
        header("Location: $base/");
        exit;
    }
}

function open() {
    $conn = mysqli_connect("localhost", "root", "", "simdb_alat_ji");
    if (!$conn) {
        die("Connection failed: " . mysqli_connect_error());
    }
    return $conn;
}

function db_select($sql, $types = "", ...$params) {
    $conn = open();
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt === false) return false;

    if ($types) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }

    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return false;
    }

    $result = mysqli_stmt_get_result($stmt);
    mysqli_stmt_close($stmt);

    return $result;
}

function call_sp($sp_name, $params = [], $out_params = []) {
    $conn = open();

    $placeholders = [];
    $bind_types = "";
    $bind_values = [];

    foreach ($params as $param) {
        $placeholders[] = "?";
        $bind_types .= $param['type'];
        $bind_values[] = $param['value'];
    }

    foreach ($out_params as $out) {
        $placeholders[] = "@" . $out;
    }

    $sql = "CALL $sp_name(" . implode(", ", $placeholders) . ")";

    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt === false) {
        return ['ok' => false, 'error' => mysqli_error($conn)];
    }

    if ($bind_types) {
        mysqli_stmt_bind_param($stmt, $bind_types, ...$bind_values);
    }

    if (!mysqli_stmt_execute($stmt)) {
        $error = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        return ['ok' => false, 'error' => $error];
    }

    mysqli_stmt_close($stmt);

    $result = ['ok' => true];

    if (!empty($out_params)) {
        $out_sql = "SELECT " . implode(", ", array_map(function($p) {
            return "@$p as $p";
        }, $out_params));

        $out_result = mysqli_query($conn, $out_sql);
        if ($out_result) {
            $result['output'] = mysqli_fetch_assoc($out_result);
        }
    }

    return $result;
}

/**
 * Generic logging function - uses the new sp_log_insert_ji procedure
 * 
 * @param int $user_id The ID of the user performing the action
 * @param string $action Description of the action being logged
 * @return array Result of the operation
 */
function log_action($user_id, $action) {
    return call_sp("sp_log_insert_ji", [
        ['type' => 'i', 'value' => $user_id],
        ['type' => 's', 'value' => $action]
    ]);
}

/**
 * Get the image path for an alat (equipment)
 * First checks database for image filename, then falls back to filesystem check
 * 
 * @param int $alat_id The ID of the alat
 * @return string The relative path to the image
 */
function get_alat_image($alat_id) {
    global $base;
    $img_dir = $base . "/img/";
    
    // First check database for image filename
    $result = db_select("SELECT image_alat_ji FROM alat_ji WHERE id_alat_ji = ?", "i", $alat_id);
    if ($result && $row = mysqli_fetch_assoc($result)) {
        $db_image = $row['image_alat_ji'];
        if ($db_image && file_exists($_SERVER['DOCUMENT_ROOT'] . $img_dir . $db_image)) {
            return $img_dir . $db_image;
        }
    }
    
    // Fallback: check filesystem for alat_{id}.{ext} pattern
    $extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    foreach ($extensions as $ext) {
        $filepath = $img_dir . "alat_" . $alat_id . '.' . $ext;
        if (file_exists($_SERVER['DOCUMENT_ROOT'] . $filepath)) {
            return $filepath;
        }
    }
    
    // Return default if no image found
    return $img_dir . 'alat_default.jpg';
}
