<?php
// Config/database.php

define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'kiemdinh_cntt');
define('DB_CHARSET', 'utf8mb4');

function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            try {
                $rootDsn = sprintf('mysql:host=%s;charset=%s', DB_HOST, DB_CHARSET);
                $rootPdo = new PDO($rootDsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                ]);
                $rootPdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");

                $schemaFile = __DIR__ . '/../database/schema.sql';
                if (file_exists($schemaFile)) {
                    $sql = file_get_contents($schemaFile);
                    $rootPdo->exec("USE `" . DB_NAME . "`;");
                    $rootPdo->exec($sql);
                }

                $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);
            } catch (PDOException $e2) {
                die('Lỗi kết nối CSDL: ' . $e2->getMessage());
            }
        }

        if ($pdo !== null) {
            try {
                $cols = $pdo->query("SHOW COLUMNS FROM BoTieuChuan LIKE 'ThongTu'")->fetchAll();
                if (empty($cols)) {
                    $pdo->exec("ALTER TABLE BoTieuChuan ADD COLUMN ThongTu VARCHAR(255) NULL AFTER TenBoTieuChuan");
                }
                $cols = $pdo->query("SHOW COLUMNS FROM BoTieuChuan LIKE 'NgayBanHanh'")->fetchAll();
                if (empty($cols)) {
                    $pdo->exec("ALTER TABLE BoTieuChuan ADD COLUMN NgayBanHanh DATE NULL AFTER ThongTu");
                }
                $cols = $pdo->query("SHOW COLUMNS FROM NguoiDung LIKE 'SoDienThoai'")->fetchAll();
                if (empty($cols)) {
                    $hasSdt = $pdo->query("SHOW COLUMNS FROM NguoiDung LIKE 'SDT'")->fetchAll();
                    if (!empty($hasSdt)) {
                        $pdo->exec("ALTER TABLE NguoiDung CHANGE SDT SoDienThoai VARCHAR(30) NULL");
                    } else {
                        $pdo->exec("ALTER TABLE NguoiDung ADD COLUMN SoDienThoai VARCHAR(30) NULL AFTER Email");
                    }
                }

                // Ensure columns in all tables allow VARCHAR(50) for codes
                $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
                $pdo->exec("ALTER TABLE download_logs MODIFY COLUMN MaMinhChung VARCHAR(50) NOT NULL;");
                $pdo->exec("ALTER TABLE download_logs MODIFY COLUMN MaNguoiDung VARCHAR(50) NULL;");
                $pdo->exec("ALTER TABLE audit_logs MODIFY COLUMN MaNguoiDung VARCHAR(50) NULL;");
                $pdo->exec("ALTER TABLE remember_tokens MODIFY COLUMN MaNguoiDung VARCHAR(50) NOT NULL;");
                $pdo->exec("ALTER TABLE MinhChung MODIFY COLUMN MaMinhChung VARCHAR(50) NOT NULL;");
                $pdo->exec("ALTER TABLE MinhChung MODIFY COLUMN MaTieuChi VARCHAR(50) NULL;");
                $pdo->exec("ALTER TABLE MinhChung MODIFY COLUMN MaNguoiDung VARCHAR(50) NULL;");
                $pdo->exec("ALTER TABLE TieuChi MODIFY COLUMN MaTieuChi VARCHAR(50) NOT NULL;");
                $pdo->exec("ALTER TABLE TieuChi MODIFY COLUMN MaTieuChuan VARCHAR(50) NOT NULL;");
                $pdo->exec("ALTER TABLE TieuChuan MODIFY COLUMN MaTieuChuan VARCHAR(50) NOT NULL;");
                $pdo->exec("ALTER TABLE TieuChuan MODIFY COLUMN MaBoTieuChuan VARCHAR(50) NOT NULL;");
                $pdo->exec("ALTER TABLE BoTieuChuan MODIFY COLUMN MaBoTieuChuan VARCHAR(50) NOT NULL;");
                $pdo->exec("ALTER TABLE NguoiDung MODIFY COLUMN MaNguoiDung VARCHAR(50) NOT NULL;");
                $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

            } catch (Exception $ex) {}
        }
    }

    return $pdo;
}
