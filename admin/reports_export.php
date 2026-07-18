<?php
require_once __DIR__ . '/../includes/helpers.php';
require_roles(['admin']);
require_once __DIR__ . '/../includes/data.php';

$complete = 0;
$need = 0;
$missing = 0;
$priorityCriteria = [];

foreach ($criteria as $item) {
    if ($item['status'] === 'Đủ minh chứng') {
        $complete++;
    } elseif ($item['status'] === 'Cần bổ sung') {
        $need++;
        $priorityCriteria[] = $item;
    } else {
        $missing++;
        $priorityCriteria[] = $item;
    }
}

$exportedAt = date('d/m/Y H:i');
$exportedBy = $currentUser['name'] ?? 'Quản trị viên';
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Báo cáo thống kê kiểm định</title>
    <link rel="icon" type="image/png" href="<?= base_url('assets/images/fbu-logo.png?v=2') ?>">
    <style>
        @page {
            size: A4;
            margin: 14mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #eef6ff;
            color: #0b2d57;
            font-family: "Segoe UI", Arial, sans-serif;
            font-size: 13px;
            line-height: 1.45;
        }

        .print-toolbar {
            position: sticky;
            top: 0;
            z-index: 10;
            display: flex;
            justify-content: center;
            gap: 10px;
            padding: 12px;
            background: #ffffff;
            border-bottom: 1px solid #d8e6f7;
        }

        .print-toolbar button {
            min-height: 38px;
            border: 1px solid #2f64ad;
            border-radius: 8px;
            background: #2f64ad;
            color: #ffffff;
            padding: 0 16px;
            font-weight: 700;
            cursor: pointer;
        }

        .print-toolbar .secondary {
            background: #ffffff;
            color: #2f64ad;
        }

        .page {
            width: 210mm;
            min-height: 297mm;
            margin: 18px auto;
            padding: 18mm;
            background: #ffffff;
            box-shadow: 0 16px 42px rgba(12, 48, 95, 0.16);
        }

        .report-header {
            display: grid;
            grid-template-columns: 72px 1fr;
            gap: 16px;
            align-items: center;
            padding-bottom: 14px;
            border-bottom: 3px solid #2f64ad;
        }

        .report-logo {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            object-fit: contain;
            border: 2px solid #58b7e6;
            padding: 4px;
        }

        .school {
            margin: 0 0 4px;
            color: #2f64ad;
            font-weight: 800;
            text-transform: uppercase;
        }

        h1 {
            margin: 0;
            font-size: 22px;
            color: #082f66;
            text-transform: uppercase;
        }

        .subtitle {
            margin: 6px 0 0;
            color: #526b8c;
        }

        .meta {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 8px 18px;
            margin: 16px 0 18px;
            padding: 12px;
            border: 1px solid #d8e6f7;
            border-radius: 8px;
            background: #f8fbff;
        }

        .meta strong {
            color: #082f66;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 18px;
        }

        .summary-card {
            border: 1px solid #d8e6f7;
            border-left: 5px solid #2f64ad;
            border-radius: 8px;
            padding: 12px;
            background: #ffffff;
        }

        .summary-card.green {
            border-left-color: #15945f;
            background: #f0fbf5;
        }

        .summary-card.amber {
            border-left-color: #f8ad26;
            background: #fff8e8;
        }

        .summary-card.red {
            border-left-color: #ed2a21;
            background: #fff0ef;
        }

        .summary-card span {
            display: block;
            font-weight: 700;
            color: #526b8c;
        }

        .summary-card strong {
            display: block;
            margin-top: 6px;
            font-size: 28px;
            color: #082f66;
        }

        h2 {
            margin: 22px 0 10px;
            font-size: 17px;
            color: #082f66;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            page-break-inside: auto;
        }

        thead {
            display: table-header-group;
        }

        tr {
            page-break-inside: avoid;
        }

        th,
        td {
            padding: 8px 9px;
            border: 1px solid #d8e6f7;
            vertical-align: top;
            text-align: left;
        }

        th {
            background: #eaf4ff;
            color: #064a97;
            font-size: 12px;
            text-transform: uppercase;
        }

        .status {
            display: inline-block;
            border-radius: 999px;
            padding: 4px 9px;
            font-size: 11px;
            font-weight: 800;
            white-space: nowrap;
        }

        .status-success {
            background: #15945f;
            color: #ffffff;
        }

        .status-warning {
            background: #ffc107;
            color: #101828;
        }

        .status-danger {
            background: #e33448;
            color: #ffffff;
        }

        .footer-note {
            margin-top: 24px;
            padding-top: 12px;
            border-top: 1px solid #d8e6f7;
            color: #526b8c;
            font-size: 12px;
            text-align: center;
        }

        @media print {
            body {
                background: #ffffff;
            }

            .print-toolbar {
                display: none;
            }

            .page {
                width: auto;
                min-height: auto;
                margin: 0;
                padding: 0;
                box-shadow: none;
            }
        }
    </style>
</head>
<body>
<div class="print-toolbar">
    <button type="button" onclick="window.print()">In / Lưu PDF</button>
    <button class="secondary" type="button" onclick="window.close()">Đóng</button>
</div>

<main class="page">
    <header class="report-header">
        <img class="report-logo" src="<?= base_url('assets/images/fbu-logo.png') ?>" alt="FBU">
        <div>
            <p class="school"><?= htmlspecialchars($trainingProgram['school']) ?></p>
            <h1>Báo cáo thống kê phục vụ kiểm định</h1>
            <p class="subtitle">Cơ sở dữ liệu minh chứng CTĐT ngành Công nghệ thông tin</p>
        </div>
    </header>

    <section class="meta">
        <div><strong>Chương trình đào tạo:</strong> <?= htmlspecialchars($trainingProgram['name']) ?></div>
        <div><strong>Mã ngành:</strong> <?= htmlspecialchars($trainingProgram['code']) ?></div>
        <div><strong>Chu kỳ kiểm định:</strong> <?= htmlspecialchars($trainingProgram['cycle']) ?></div>
        <div><strong>Thời điểm xuất:</strong> <?= htmlspecialchars($exportedAt) ?></div>
        <div><strong>Người xuất báo cáo:</strong> <?= htmlspecialchars($exportedBy) ?></div>
        <div><strong>Tổng số tiêu chí:</strong> <?= count($criteria) ?></div>
    </section>

    <section class="summary-grid">
        <div class="summary-card green">
            <span>Tiêu chí đủ minh chứng</span>
            <strong><?= $complete ?></strong>
        </div>
        <div class="summary-card amber">
            <span>Cần bổ sung</span>
            <strong><?= $need ?></strong>
        </div>
        <div class="summary-card red">
            <span>Thiếu minh chứng</span>
            <strong><?= $missing ?></strong>
        </div>
    </section>

    <section>
        <h2>Số lượng minh chứng theo tiêu chuẩn</h2>
        <table>
            <thead>
            <tr>
                <th style="width: 80px;">Mã</th>
                <th>Tên tiêu chuẩn</th>
                <th style="width: 90px;">Tiêu chí</th>
                <th style="width: 110px;">Minh chứng</th>
                <th style="width: 135px;">Trạng thái</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($standards as $standard): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($standard['code']) ?></strong></td>
                    <td><?= htmlspecialchars($standard['name']) ?></td>
                    <td><?= (int) $standard['criteria'] ?></td>
                    <td><?= (int) $standard['evidences'] ?></td>
                    <td>
                        <span class="status status-<?= htmlspecialchars(status_class($standard['status'])) ?>">
                            <?= htmlspecialchars($standard['status']) ?>
                        </span>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>

    <section>
        <h2>Tiêu chí cần ưu tiên xử lý</h2>
        <table>
            <thead>
            <tr>
                <th style="width: 80px;">Mã</th>
                <th>Nội dung tiêu chí</th>
                <th style="width: 170px;">Đơn vị phụ trách</th>
                <th style="width: 90px;">Minh chứng</th>
                <th style="width: 135px;">Trạng thái</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($priorityCriteria as $item): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($item['code']) ?></strong></td>
                    <td><?= htmlspecialchars($item['name']) ?></td>
                    <td><?= htmlspecialchars($item['owner']) ?></td>
                    <td><?= (int) $item['evidences'] ?></td>
                    <td>
                        <span class="status status-<?= htmlspecialchars(status_class($item['status'])) ?>">
                            <?= htmlspecialchars($item['status']) ?>
                        </span>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$priorityCriteria): ?>
                <tr>
                    <td colspan="5">Không có tiêu chí cần ưu tiên xử lý.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </section>

    <p class="footer-note">
        Copyright 2026 Trường Đại học Tài chính Ngân hàng Hà Nội - Viện Công nghệ thông tin
    </p>
</main>

<script>
    window.addEventListener('load', function () {
        setTimeout(function () {
            window.print();
        }, 350);
    });
</script>
</body>
</html>
