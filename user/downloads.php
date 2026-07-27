<?php
require_once __DIR__ . '/../includes/helpers.php';
require_login();
require_once __DIR__ . '/../includes/data.php';

$stmt = db()->query("
    SELECT
        DATE_FORMAT(dl.ngay_tai, '%d/%m/%Y %H:%i') AS downloaded_time,
        e.ma_minh_chung AS code,
        e.tieu_de AS title,
        COALESCE(u.ho_ten, 'Khách') AS downloader,
        ef.loai_file AS file_type,
        ef.id AS file_id
    FROM download_logs dl
    JOIN file_minh_chung ef ON ef.id = dl.id_file_minh_chung
    JOIN minh_chung e ON e.id = ef.id_minh_chung
    LEFT JOIN nguoi_dung u ON u.id = dl.id_nguoi_dung
    ORDER BY dl.ngay_tai DESC, dl.id DESC
    LIMIT 30
");
$downloadRows = $stmt->fetchAll();

$pageTitle = page_title('Lịch sử tải');
$heading = 'Lịch sử tải minh chứng';
include __DIR__ . '/../includes/header.php';
?>
<div class="panel">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h5 mb-0">Các lượt tải gần đây</h2>
        <a class="btn btn-primary" href="<?= base_url('user/search.php') ?>"><i class="bi bi-search me-1"></i> Tìm kiếm thêm</a>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead>
            <tr>
                <th>Thời gian</th>
                <th>Mã minh chứng</th>
                <th>Tên minh chứng</th>
                <th>Người tải</th>
                <th>Định dạng</th>
                <th class="text-end">Tải lại</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($downloadRows as $item): ?>
                <tr>
                    <td><?= htmlspecialchars($item['downloaded_time']) ?></td>
                    <td class="fw-bold"><?= htmlspecialchars($item['code']) ?></td>
                    <td><?= htmlspecialchars($item['title']) ?></td>
                    <td><?= htmlspecialchars($item['downloader']) ?></td>
                    <td><span class="badge text-bg-light text-dark"><?= htmlspecialchars($item['file_type']) ?></span></td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-success" href="<?= base_url('user/download.php?id=' . (int) $item['file_id']) ?>"><i class="bi bi-download"></i></a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$downloadRows): ?>
                <tr>
                    <td colspan="6" class="text-center text-secondary">Chưa có lượt tải minh chứng.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
