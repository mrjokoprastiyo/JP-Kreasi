<?php
require_once '../config.php';
require_once '../core/auth.php';
require_once '../core/db.php';

// Proteksi: Hanya admin yang bisa update user
if ($_SESSION['role'] !== 'admin') {
    die("Unauthorized access.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id       = (int)$_POST['id'];
    $fullname = $_POST['fullname'];
    $email    = $_POST['email'];
    $role     = $_POST['role'];
    $status   = $_POST['status'];
    $password = $_POST['password'];

    try {
        // 1. Update data dasar
        $sql = "UPDATE users SET fullname = ?, email = ?, role = ?, status = ? WHERE id = ?";
        $params = [$fullname, $email, $role, $status, $id];
        DB::exec($sql, $params);

        // 2. Jika password diisi, update password (hash)
        if (!empty($password)) {
            if (strlen($password) < 8) {
                header("Location: user-edit.php?id=$id&err=Password minimal 8 karakter");
                exit;
            }
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
            DB::exec("UPDATE users SET password = ? WHERE id = ?", [$hashedPassword, $id]);
        }

        header("Location: user-list.php?msg=User updated successfully");
        exit;

    } catch (Exception $e) {
        header("Location: user-edit.php?id=$id&err=Terjadi kesalahan: " . $e->getMessage());
        exit;
    }
} else {
    header("Location: user-list.php");
    exit;
}
