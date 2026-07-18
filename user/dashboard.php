<?php
require_once __DIR__ . '/../includes/helpers.php';
require_roles(['admin']);
require_once __DIR__ . '/../includes/data.php';
$pageTitle = page_title('Trang người dùng');
$heading = 'Không gian tra cứu minh chứng';
include __DIR__ . '/../includes/header.php';
?>
<div class="row g-4"><div class="col-xl-8"><div class="panel"><h2 class="h5 mb-3">Minh chứng mới cập nhật</h2><div class="table-responsive"><table class="table"><thead><tr><th>Mã</th><th>Tên minh chứng</th><th>Tiêu chí</th><th>Cập nhật</th><th class="text-end">Tải</th></tr></thead><tbody><?php foreach ($evidences as $item): ?><tr><td class="fw-bold"><?= htmlspecialchars($item['code']) ?></td><td><?= htmlspecialchars($item['name']) ?></td><td><?= htmlspecialchars($item['criteria']) ?></td><td><?= htmlspecialchars($item['updated']) ?></td><td class="text-end"><a class="btn btn-sm btn-outline-success" href="<?= base_url('user/downloads.php') ?>"><i class="bi bi-download"></i></a></td></tr><?php endforeach; ?></tbody></table></div></div></div><div class="col-xl-4"><div class="panel"><h2 class="h5 mb-3">Tra cứu nhanh</h2><form action="<?= base_url('user/search.php') ?>" method="get"><div class="mb-3"><label class="form-label">Từ khóa</label><input class="form-control" name="q" placeholder="Mã, tên minh chứng, tiêu chí"></div><button class="btn btn-primary w-100"><i class="bi bi-search me-1"></i> Tìm kiếm</button></form></div></div></div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
