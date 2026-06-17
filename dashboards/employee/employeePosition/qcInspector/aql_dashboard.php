<?php
session_start();
require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../config/constants.php';
require_once __DIR__ . '/../../../../includes/auth_check.php';

$pageTitle = 'AQL Lot Inspection';

// Fetch pending AQL inspections for QC inspector
$stmt = $pdo->prepare("
    SELECT li.*, o.order_id, o.total_price, ow.assigned_employee,
           u.full_name AS customer_name,
           (SELECT SUM(quantity) FROM order_details WHERE order_id = o.order_id) AS total_qty
    FROM qc_lot_inspections li
    JOIN orders o ON li.order_id = o.order_id
    JOIN order_workflow ow ON li.workflow_id = ow.workflow_id
    JOIN users u ON o.user_id = u.user_id
    WHERE li.verdict = 'Pending'
    ORDER BY li.created_at ASC
");
$stmt->execute();
$pending = $stmt->fetchAll();

// Fetch recent inspections by this inspector
$stmt = $pdo->prepare("
    SELECT li.*, o.order_id
    FROM qc_lot_inspections li
    JOIN orders o ON li.order_id = o.order_id
    WHERE li.inspector_id = ?
    ORDER BY li.updated_at DESC
    LIMIT 10
");
$stmt->execute([$_SESSION['user_id']]);
$my_recent = $stmt->fetchAll();

// KPIs
$stmt = $pdo->prepare("SELECT COUNT(*) FROM qc_lot_inspections WHERE inspector_id = ? AND verdict = 'Pending'");
$stmt->execute([$_SESSION['user_id']]);
$pending_count = $stmt->fetchColumn();
$stmt = $pdo->prepare("SELECT COUNT(*) FROM qc_lot_inspections WHERE inspector_id = ? AND verdict = 'Passed' AND DATE(inspected_at) = CURDATE()");
$stmt->execute([$_SESSION['user_id']]);
$passed_today = $stmt->fetchColumn();
$stmt = $pdo->prepare("SELECT COUNT(*) FROM qc_lot_inspections WHERE inspector_id = ? AND verdict = 'Failed' AND DATE(inspected_at) = CURDATE()");
$stmt->execute([$_SESSION['user_id']]);
$failed_today = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - QC Inspector</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="/public/assets/css/dashboard-modern.css">
    <style>
        .aql-badge { font-size: 0.8rem; padding: 2px 8px; border-radius: 12px; }
        .aql-pass { background: #d1fae5; color: #065f46; }
        .aql-fail { background: #fee2e2; color: #991b1b; }
        .aql-pending { background: #fef3c7; color: #92400e; }
        .lot-card { border: 1px solid #e2e8f0; border-radius: 16px; padding: 1.5rem; margin-bottom: 1rem; background: #fff; transition: box-shadow 0.2s; }
        .lot-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        .kpi-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../../../includes/sidebar_qc_inspector.php'; ?>
    <div class="main-content">
        <?php include __DIR__ . '/../../../../includes/topnav.php'; ?>
        <div class="content-container">

            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="fw-bold mb-0"><i class="fas fa-clipboard-check me-2" style="color: #10b981;"></i><?= $pageTitle ?></h4>
            </div>

            <!-- KPI Cards -->
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm" style="border-radius: 16px;">
                        <div class="card-body d-flex align-items-center gap-3">
                            <div class="kpi-icon" style="background: #fef3c7;"><i class="fas fa-hourglass-half" style="color: #d97706;"></i></div>
                            <div><div class="text-muted small">Pending Inspections</div><div class="fs-4 fw-bold"><?= $pending_count ?></div></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm" style="border-radius: 16px;">
                        <div class="card-body d-flex align-items-center gap-3">
                            <div class="kpi-icon" style="background: #d1fae5;"><i class="fas fa-check" style="color: #059669;"></i></div>
                            <div><div class="text-muted small">Passed Today</div><div class="fs-4 fw-bold"><?= $passed_today ?></div></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm" style="border-radius: 16px;">
                        <div class="card-body d-flex align-items-center gap-3">
                            <div class="kpi-icon" style="background: #fee2e2;"><i class="fas fa-times" style="color: #dc2626;"></i></div>
                            <div><div class="text-muted small">Failed Today</div><div class="fs-4 fw-bold"><?= $failed_today ?></div></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pending AQL Inspections -->
            <div class="card border-0 shadow-sm mb-4" style="border-radius: 16px;">
                <div class="card-header bg-transparent border-bottom-0 pt-3 px-4">
                    <h5 class="fw-semibold mb-0"><i class="fas fa-clock me-2" style="color: #f59e0b;"></i>Pending Lot Inspections</h5>
                </div>
                <div class="card-body p-4">
                    <?php if (count($pending) === 0): ?>
                        <div class="text-center text-muted py-4"><i class="fas fa-check-circle fa-2x mb-2" style="color: #10b981;"></i><p class="mb-0">No pending inspections.</p></div>
                    <?php else: ?>
                        <div class="row g-3">
                            <?php foreach ($pending as $lot): ?>
                                <?php
                                $acRe = getAQLAcceptReject($lot['aql_level'], $lot['sample_size']);
                                ?>
                                <div class="col-md-6 col-lg-4">
                                    <div class="lot-card">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div>
                                                <span class="aql-badge aql-pending"><i class="fas fa-clock me-1"></i>Pending</span>
                                                <span class="badge bg-light text-dark ms-1">Lot #<?= $lot['lot_inspection_id'] ?></span>
                                            </div>
                                            <span class="fw-medium">AQL <?= $lot['aql_level'] ?></span>
                                        </div>
                                        <h6 class="fw-semibold mb-1">Order #<?= $lot['order_id'] ?></h6>
                                        <div class="text-muted small mb-2"><?= htmlspecialchars($lot['customer_name']) ?></div>
                                        <div class="d-flex gap-3 mb-3 small">
                                            <span><i class="fas fa-box me-1 text-muted"></i>Lot: <strong><?= $lot['lot_size'] ?></strong></span>
                                            <span><i class="fas fa-search me-1 text-muted"></i>Sample: <strong><?= $lot['sample_size'] ?></strong></span>
                                            <span><i class="fas fa-check-circle me-1 text-muted"></i>Ac/Re: <strong><?= $acRe[0] ?>/<?= $acRe[1] ?></strong></span>
                                        </div>
                                        <a href="aql_inspect.php?lot_inspection_id=<?= $lot['lot_inspection_id'] ?>" class="btn btn-primary w-100 btn-sm"><i class="fas fa-clipboard-check me-1"></i>Inspect Lot</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- My Recent Inspections -->
            <div class="card border-0 shadow-sm" style="border-radius: 16px;">
                <div class="card-header bg-transparent border-bottom-0 pt-3 px-4">
                    <h5 class="fw-semibold mb-0"><i class="fas fa-history me-2" style="color: #6366f1;"></i>My Recent Inspections</h5>
                </div>
                <div class="card-body p-4">
                    <?php if (count($my_recent) === 0): ?>
                        <div class="text-muted small py-2">No inspections performed yet.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle small">
                                <thead class="text-muted">
                                    <tr><th>Lot #</th><th>Order</th><th>Lot/Sample</th><th>Defects C/M/m</th><th>Verdict</th><th>Date</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($my_recent as $lot): ?>
                                        <tr>
                                            <td class="fw-medium">#<?= $lot['lot_inspection_id'] ?></td>
                                            <td>#<?= $lot['order_id'] ?></td>
                                            <td><?= $lot['lot_size'] ?>/<?= $lot['sample_size'] ?></td>
                                            <td><?= $lot['critical_defects'] ?>/<?= $lot['major_defects'] ?>/<?= $lot['minor_defects'] ?></td>
                                            <td>
                                                <?php if ($lot['verdict'] === 'Passed'): ?>
                                                    <span class="aql-badge aql-pass">Passed</span>
                                                <?php elseif ($lot['verdict'] === 'Failed'): ?>
                                                    <span class="aql-badge aql-fail">Failed</span>
                                                <?php else: ?>
                                                    <span class="aql-badge aql-pending">Pending</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-muted"><?= $lot['inspected_at'] ? date('M j, g:i A', strtotime($lot['inspected_at'])) : '—' ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
