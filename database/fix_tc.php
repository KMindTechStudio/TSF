<?php
require_once __DIR__ . '/../config/database.php';

$pdo = db();
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");

// 1. Clean TieuChuan
$rows = $pdo->query("SELECT MaTieuChuan FROM TieuChuan")->fetchAll();
foreach ($rows as $r) {
    $oldCode = (string) $r['MaTieuChuan'];
    $cleanCode = preg_replace('/^(TC)+/', 'TC', $oldCode);
    if (is_numeric($cleanCode)) {
        $cleanCode = 'TC' . str_pad($cleanCode, 2, '0', STR_PAD_LEFT);
    }
    if ($cleanCode !== $oldCode) {
        $stmt1 = $pdo->prepare("UPDATE TieuChi SET MaTieuChuan = :new_code WHERE MaTieuChuan = :old_code");
        $stmt1->execute(['new_code' => $cleanCode, 'old_code' => $oldCode]);

        $stmt2 = $pdo->prepare("UPDATE TieuChuan SET MaTieuChuan = :new_code WHERE MaTieuChuan = :old_code");
        $stmt2->execute(['new_code' => $cleanCode, 'old_code' => $oldCode]);
    }
}

// 2. Clean BoTieuChuan
$sets = $pdo->query("SELECT MaBoTieuChuan FROM BoTieuChuan")->fetchAll();
foreach ($sets as $s) {
    $oldSetCode = (string) $s['MaBoTieuChuan'];
    $cleanSetCode = preg_replace('/^(BTC)+/', 'BTC', $oldSetCode);
    if (is_numeric($cleanSetCode)) {
        $cleanSetCode = 'BTC' . str_pad($cleanSetCode, 2, '0', STR_PAD_LEFT);
    }
    if ($cleanSetCode !== $oldSetCode) {
        $stmt1 = $pdo->prepare("UPDATE TieuChuan SET MaBoTieuChuan = :new_code WHERE MaBoTieuChuan = :old_code");
        $stmt1->execute(['new_code' => $cleanSetCode, 'old_code' => $oldSetCode]);

        $stmt2 = $pdo->prepare("UPDATE BoTieuChuan SET MaBoTieuChuan = :new_code WHERE MaBoTieuChuan = :old_code");
        $stmt2->execute(['new_code' => $cleanSetCode, 'old_code' => $oldSetCode]);
    }
}

// 3. Clean TieuChi
$cris = $pdo->query("SELECT MaTieuChi FROM TieuChi")->fetchAll();
foreach ($cris as $c) {
    $oldCriCode = (string) $c['MaTieuChi'];
    $cleanCriCode = preg_replace('/^(TCHI|TC)+/', 'TC', $oldCriCode);
    if (is_numeric($cleanCriCode)) {
        $cleanCriCode = 'TC' . str_pad($cleanCriCode, 2, '0', STR_PAD_LEFT);
    }
    if ($cleanCriCode !== $oldCriCode) {
        $stmt1 = $pdo->prepare("UPDATE MinhChung SET MaTieuChi = :new_code WHERE MaTieuChi = :old_code");
        $stmt1->execute(['new_code' => $cleanCriCode, 'old_code' => $oldCriCode]);

        $stmt2 = $pdo->prepare("UPDATE TieuChi SET MaTieuChi = :new_code WHERE MaTieuChi = :old_code");
        $stmt2->execute(['new_code' => $cleanCriCode, 'old_code' => $oldCriCode]);
    }
}

// 4. Clean MinhChung
$evis = $pdo->query("SELECT MaMinhChung FROM MinhChung")->fetchAll();
foreach ($evis as $e) {
    $oldEviCode = (string) $e['MaMinhChung'];
    $cleanEviCode = preg_replace('/^(MC)+/', 'MC', $oldEviCode);
    if (is_numeric($cleanEviCode)) {
        $cleanEviCode = 'MC' . str_pad($cleanEviCode, 2, '0', STR_PAD_LEFT);
    }
    if ($cleanEviCode !== $oldEviCode) {
        $stmt = $pdo->prepare("UPDATE MinhChung SET MaMinhChung = :new_code WHERE MaMinhChung = :old_code");
        $stmt->execute(['new_code' => $cleanEviCode, 'old_code' => $oldEviCode]);
    }
}

// 5. Clean NguoiDung
$users = $pdo->query("SELECT MaNguoiDung FROM NguoiDung")->fetchAll();
foreach ($users as $u) {
    $oldUserCode = (string) $u['MaNguoiDung'];
    $cleanUserCode = preg_replace('/^(ND)+/', 'ND', $oldUserCode);
    if (is_numeric($cleanUserCode)) {
        $cleanUserCode = 'ND' . str_pad($cleanUserCode, 3, '0', STR_PAD_LEFT);
    }
    if ($cleanUserCode !== $oldUserCode) {
        $stmt1 = $pdo->prepare("UPDATE MinhChung SET MaNguoiDung = :new_code WHERE MaNguoiDung = :old_code");
        $stmt1->execute(['new_code' => $cleanUserCode, 'old_code' => $oldUserCode]);

        $stmt2 = $pdo->prepare("UPDATE remember_tokens SET MaNguoiDung = :new_code WHERE MaNguoiDung = :old_code");
        $stmt2->execute(['new_code' => $cleanUserCode, 'old_code' => $oldUserCode]);

        $stmt3 = $pdo->prepare("UPDATE audit_logs SET MaNguoiDung = :new_code WHERE MaNguoiDung = :old_code");
        $stmt3->execute(['new_code' => $cleanUserCode, 'old_code' => $oldUserCode]);

        $stmt4 = $pdo->prepare("UPDATE NguoiDung SET MaNguoiDung = :new_code WHERE MaNguoiDung = :old_code");
        $stmt4->execute(['new_code' => $cleanUserCode, 'old_code' => $oldUserCode]);
    }
}

$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
