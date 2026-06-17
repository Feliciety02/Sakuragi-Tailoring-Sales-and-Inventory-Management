<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/role_admin_only.php';

$pageTitle = 'AQL Sampling QC';

// Handle AQL config save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_aql_config'])) {
    $order_id = (int)$_POST['order_id'];
    $aql_level = $_POST['aql_level'];
    $inspection_level = $_POST['inspection_level'];
    $crit_allowed = (int)$_POST['critical_allowed'];
    try {
        $stmt = $pdo->prepare("
            INSERT INTO aql_config (order_id, aql_level, inspection_level, critical_allowed)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE aql_level = VALUES(aql_level), inspection_level = VALUES(inspection_level), critical_allowed = VALUES(critical_allowed)
        ");
        $stmt->execute([$order_id, $aql_level, $inspection_level, $crit_allowed]);
        $success = 'AQL configuration saved.';
    } catch (Exception $e) {
        $error = 'Failed to save AQL config: ' . $e->getMessage();
    }
}

// Handle start new inspection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_inspection'])) {
    $order_id = (int)$_POST['order_id'];
    $workflow_id = (int)$_POST['workflow_id'];
    $lot_size = (int)$_POST['lot_size'];

    // Get AQL config or defaults
    $stmt = $pdo->prepare("SELECT aql_level, inspection_level FROM aql_config WHERE order_id = ?");
    $stmt->execute([$order_id]);
    $cfg = $stmt->fetch();

    $aql_level = $cfg['aql_level'] ?? '2.5';
    $inspection_level = $cfg['inspection_level'] ?? 'II';
    $sample_size = getAQLSampleSize($lot_size, $inspection_level);

    try {
        $stmt = $pdo->prepare("
            INSERT INTO qc_lot_inspections (order_id, workflow_id, inspector_id, lot_size, sample_size, aql_level)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$order_id, $workflow_id, $_SESSION['user_id'], $lot_size, $sample_size, $aql_level]);
        $lot_id = $pdo->lastInsertId();
        $success = "Lot inspection #{$lot_id} started. Sample size: {$sample_size}";
    } catch (Exception $e) {
        $error = 'Failed to start inspection: ' . $e->getMessage();
    }
}

// Handle pass/fail verdict
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_verdict'])) {
    $lot_inspection_id = (int)$_POST['lot_inspection_id'];
    $critical_defects = (int)$_POST['critical_defects'];
    $major_defects = (int)$_POST['major_defects'];
    $minor_defects = (int)$_POST['minor_defects'];
    $notes = $_POST['notes'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM qc_lot_inspections WHERE lot_inspection_id = ?");
    $stmt->execute([$lot_inspection_id]);
    $lot = $stmt->fetch();

    if ($lot) {
        $verdict = getAQLVerdict($critical_defects, $major_defects, $minor_defects, $lot['aql_level'], $lot['sample_size']);
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                UPDATE qc_lot_inspections
                SET critical_defects = ?, major_defects = ?, minor_defects = ?,
                    verdict = ?, notes = ?, inspected_at = NOW()
                WHERE lot_inspection_id = ?
            ");
            $stmt->execute([$critical_defects, $major_defects, $minor_defects, $verdict, $notes, $lot_inspection_id]);

            // Update workflow
            $target_stage = $verdict === 'Passed' ? STAGE_PACKAGING : STAGE_REWORK;
            $stmt = $pdo->prepare("UPDATE order_workflow SET stage = ?, completed_at = IF(? = 'Passed', NOW(), NULL) WHERE workflow_id = ?");
            $stmt->execute([$target_stage, $verdict, $lot['workflow_id']]);

            // Log in garment_tracking / garment_log
            $stmt = $pdo->prepare("SELECT order_detail_id FROM garment_tracking WHERE order_id = ? AND stage = ?");
            $stmt->execute([$lot['order_id'], STAGE_QUALITY_INSPECTION]);
            $items = $stmt->fetchAll();
            foreach ($items as $item) {
                $stmt = $pdo->prepare("UPDATE garment_tracking SET stage = ?, employee_id = ?, updated_at = NOW() WHERE order_detail_id = ?");
                $stmt->execute([$target_stage, $_SESSION['user_id'], $item['order_detail_id']]);

                $stmt = $pdo->prepare("INSERT INTO garment_log (order_detail_id, order_id, from_stage, to_stage, employee_id, notes) VALUES (?, ?, ?, ?, ?, ?)");
                $from = $verdict === 'Passed' ? STAGE_QUALITY_INSPECTION : STAGE_QUALITY_INSPECTION;
                $stmt->execute([$item['order_detail_id'], $lot['order_id'], $from, $target_stage, $_SESSION['user_id'], "AQL {$verdict}: {$critical_defects}C/{$major_defects}M/{$minor_defects}m"]);
            }

            // If passed, also update order
            if ($verdict === 'Passed') {
                // Check if all items passed QC
                $stmt = $pdo->prepare("SELECT COUNT(*) as remaining FROM garment_tracking WHERE order_id = ? AND stage NOT IN (?, ?)");
                $stmt->execute([$lot['order_id'], STAGE_PACKAGING, STAGE_READY_PICKUP, STAGE_COMPLETED]);
                if ($stmt->fetch()['remaining'] == 0) {
                    $pdo->prepare("UPDATE orders SET status = ? WHERE order_id = ?")->execute([ORDER_IN_PROGRESS, $lot['order_id']]);
                }
            }

            // If failed, log rework
            if ($verdict === 'Failed') {
                $stmt = $pdo->prepare("INSERT INTO rework_log (order_id, triggered_by, from_stage, to_stage, reason, notes) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$lot['order_id'], $_SESSION['user_id'], STAGE_QUALITY_INSPECTION, STAGE_REWORK, "AQL Failed", "AQL Lot #{$lot_inspection_id}: {$critical_defects}C/{$major_defects}M/{$minor_defects}m defects"]);
            }

            $pdo->commit();
            $success = "Lot #{$lot_inspection_id} {$verdict}.";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Failed to update verdict: ' . $e->getMessage();
        }
    }
}

// Fetch orders in Quality Inspection stage
$stmt = $pdo->query("
    SELECT o.order_id, o.order_date, o.total_price,
           ow.workflow_id, ow.stage, ow.assigned_employee,
           u.full_name AS customer_name,
           (SELECT COUNT(*) FROM order_details WHERE order_id = o.order_id) AS total_items,
           COALESCE((SELECT SUM(quantity) FROM order_details WHERE order_id = o.order_id), 0) AS total_qty,
           ac.aql_level, ac.inspection_level
    FROM orders o
    JOIN order_workflow ow ON o.order_id = ow.order_id
    JOIN users u ON o.user_id = u.user_id
    LEFT JOIN aql_config ac ON o.order_id = ac.order_id
    WHERE ow.stage = '" . STAGE_QUALITY_INSPECTION . "'
    ORDER BY o.order_date DESC
");

$orders = $stmt->fetchAll();

// Fetch recent lot inspections
$stmt = $pdo->query("
    SELECT li.*, u.full_name AS inspector_name, o.order_id AS ref_order
    FROM qc_lot_inspections li
    JOIN users u ON li.inspector_id = u.user_id
    JOIN orders o ON li.order_id = o.order_id
    ORDER BY li.created_at DESC
    LIMIT 20
");
$recent_lots = $stmt->fetchAll();

// KPIs
$stmt = $pdo->query("SELECT COUNT(*) FROM qc_lot_inspections WHERE verdict = 'Pending'");
$pending_lots = $stmt->fetchColumn();
$stmt = $pdo->query("SELECT COUNT(*) FROM qc_lot_inspections WHERE verdict = 'Passed' AND DATE(inspected_at) = CURDATE()");
$passed_today = $stmt->fetchColumn();
$stmt = $pdo->query("SELECT COUNT(*) FROM qc_lot_inspections WHERE verdict = 'Failed' AND DATE(inspected_at) = CURDATE()");
$failed_today = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - Sakuragi Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="/public/assets/css/dashboard-modern.css">
    <style>
        .aql-badge { font-size: 0.8rem; padding: 2px 8px; border-radius: 12px; }
        .aql-pass { background: #d1fae5; color: #065f46; }
        .aql-fail { background: #fee2e2; color: #991b1b; }
        .aql-pending { background: #fef3c7; color: #92400e; }
        .defect-input { width: 80px; text-align: center; }
        .lot-card { border: 1px solid #e2e8f0; border-radius: 16px; padding: 1.5rem; margin-bottom: 1rem; background: #fff; transition: box-shadow 0.2s; }
        .lot-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        .kpi-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../includes/sidebar_admin.php'; ?>
    <div class="main-content">
        <?php include __DIR__ . '/../../includes/topnav.php'; ?>
        <div class="content-container">
            <?php if (isset($success)): ?>
                <div class="alert alert-success alert-dismissible fade show"><?= htmlspecialchars($success) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
            <?php endif; ?>
            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show"><?= htmlspecialchars($error) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
            <?php endif; ?>

            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="fw-bold mb-0"><?= $pageTitle ?></h4>
            </div>

            <!-- KPI Cards -->
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm" style="border-radius: 16px;">
                        <div class="card-body d-flex align-items-center gap-3">
                            <div class="kpi-icon" style="background: #fef3c7;"><i class="fas fa-hourglass-half" style="color: #d97706;"></i></div>
                            <div><div class="text-muted small">Pending Lots</div><div class="fs-4 fw-bold"><?= $pending_lots ?></div></div>
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

            <!-- Orders awaiting AQL inspection -->
            <div class="card border-0 shadow-sm mb-4" style="border-radius: 16px;">
                <div class="card-header bg-transparent border-bottom-0 pt-3 px-4">
                    <h5 class="fw-semibold mb-0"><i class="fas fa-clipboard-list me-2" style="color: #2563eb;"></i>Orders Awaiting AQL Inspection</h5>
                </div>
                <div class="card-body p-4">
                    <?php if (count($orders) === 0): ?>
                        <div class="text-center text-muted py-4"><i class="fas fa-check-circle fa-2x mb-2" style="color: #10b981;"></i><p class="mb-0">No orders awaiting inspection.</p></div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="text-muted small">
                                    <tr>
                                        <th>Order</th>
                                        <th>Customer</th>
                                        <th>Items / Qty</th>
                                        <th>AQL Config</th>
                                        <th>Total Qty</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orders as $order): ?>
                                        <?php
                                        $lotSize = (int)$order['total_qty'];
                                        $aql = $order['aql_level'] ?? '2.5';
                                        $il = $order['inspection_level'] ?? 'II';
                                        $sampleSize = getAQLSampleSize($lotSize, $il);
                                        $acRe = getAQLAcceptReject($aql, $sampleSize);
                                        ?>
                                        <tr>
                                            <td><a href="/dashboards/admin/order_details.php?order_id=<?= $order['order_id'] ?>" class="fw-medium text-decoration-none">#<?= $order['order_id'] ?></a></td>
                                            <td><?= htmlspecialchars($order['customer_name']) ?></td>
                                            <td><?= $order['total_items'] ?> items / <?= $order['total_qty'] ?> pcs</td>
                                            <td>
                                                <span class="badge bg-light text-dark me-1">AQL <?= $aql ?></span>
                                                <span class="badge bg-light text-dark">Level <?= $il ?></span>
                                            </td>
                                            <td>
                                                <span class="text-muted small">Lot: <?= $lotSize ?> | Sample: <?= $sampleSize ?>
                                                <br>Ac/Re: <?= $acRe[0] ?>/<?= $acRe[1] ?></span>
                                            </td>
                                            <td>
                                                <div class="d-flex gap-1">
                                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#configModal<?= $order['order_id'] ?>"><i class="fas fa-cog"></i></button>
                                                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#inspectModal<?= $order['order_id'] ?>"><i class="fas fa-search"></i> Inspect</button>
                                                </div>
                                            </td>
                                        </tr>
                                        <!-- AQL Config Modal -->
                                        <div class="modal fade" id="configModal<?= $order['order_id'] ?>" tabindex="-1">
                                            <div class="modal-dialog modal-dialog-centered">
                                                <form method="POST">
                                                    <div class="modal-content" style="border-radius: 16px;">
                                                        <div class="modal-header border-0 pb-0">
                                                            <h5 class="modal-title fw-bold">AQL Configuration - Order #<?= $order['order_id'] ?></h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <input type="hidden" name="order_id" value="<?= $order['order_id'] ?>">
                                                            <div class="mb-3">
                                                                <label class="form-label small fw-medium">AQL Level</label>
                                                                <select name="aql_level" class="form-select">
                                                                    <option value="1.0" <?= $aql === '1.0' ? 'selected' : '' ?>>AQL 1.0 (Strict)</option>
                                                                    <option value="2.5" <?= $aql === '2.5' ? 'selected' : '' ?>>AQL 2.5 (Normal)</option>
                                                                    <option value="4.0" <?= $aql === '4.0' ? 'selected' : '' ?>>AQL 4.0 (Loose)</option>
                                                                </select>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label small fw-medium">Inspection Level</label>
                                                                <select name="inspection_level" class="form-select">
                                                                    <option value="I" <?= $il === 'I' ? 'selected' : '' ?>>Level I (Reduced)</option>
                                                                    <option value="II" <?= $il === 'II' ? 'selected' : '' ?>>Level II (Normal)</option>
                                                                    <option value="III" <?= $il === 'III' ? 'selected' : '' ?>>Level III (Tightened)</option>
                                                                </select>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label small fw-medium">Critical Defects Allowed</label>
                                                                <input type="number" name="critical_allowed" class="form-control" value="<?= $order['critical_allowed'] ?? 0 ?>" min="0">
                                                                <div class="form-text text-muted">Zero tolerance for critical defects (recommended: 0).</div>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer border-0 pt-0">
                                                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                                                            <button type="submit" name="save_aql_config" class="btn btn-primary">Save Configuration</button>
                                                        </div>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                        <!-- Start Inspection Modal -->
                                        <div class="modal fade" id="inspectModal<?= $order['order_id'] ?>" tabindex="-1">
                                            <div class="modal-dialog modal-dialog-centered">
                                                <form method="POST">
                                                    <div class="modal-content" style="border-radius: 16px;">
                                                        <div class="modal-header border-0 pb-0">
                                                            <h5 class="modal-title fw-bold">Start AQL Inspection - Order #<?= $order['order_id'] ?></h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <input type="hidden" name="order_id" value="<?= $order['order_id'] ?>">
                                                            <input type="hidden" name="workflow_id" value="<?= $order['workflow_id'] ?>">
                                                            <div class="mb-3">
                                                                <label class="form-label small fw-medium">Lot Size (Total Pieces)</label>
                                                                <input type="number" name="lot_size" class="form-control" value="<?= $order['total_qty'] ?>" min="1" required>
                                                                <div class="form-text text-muted">Based on order: <?= $order['total_qty'] ?> pcs.</div>
                                                            </div>
                                                            <?php
                                                            $calcSample = getAQLSampleSize($order['total_qty'], $il);
                                                            $calcAcRe = getAQLAcceptReject($aql, $calcSample);
                                                            ?>
                                                            <div class="alert alert-info py-2 small mb-0">
                                                                <i class="fas fa-calculator me-1"></i>
                                                                Sample size: <strong><?= $calcSample ?></strong> |
                                                                Accept: <strong><?= $calcAcRe[0] ?></strong> |
                                                                Reject: <strong><?= $calcAcRe[1] ?></strong> (AQL <?= $aql ?>)
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer border-0 pt-0">
                                                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                                                            <button type="submit" name="start_inspection" class="btn btn-primary"><i class="fas fa-play me-1"></i>Start Inspection</button>
                                                        </div>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Lot Inspections -->
            <div class="card border-0 shadow-sm" style="border-radius: 16px;">
                <div class="card-header bg-transparent border-bottom-0 pt-3 px-4">
                    <h5 class="fw-semibold mb-0"><i class="fas fa-history me-2" style="color: #6366f1;"></i>Recent Lot Inspections</h5>
                </div>
                <div class="card-body p-4">
                    <?php if (count($recent_lots) === 0): ?>
                        <div class="text-center text-muted py-4"><p class="mb-0">No inspections yet.</p></div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="text-muted small">
                                    <tr>
                                        <th>Lot #</th>
                                        <th>Order</th>
                                        <th>Inspector</th>
                                        <th>Lot / Sample</th>
                                        <th>AQL</th>
                                        <th>Defects C/M/m</th>
                                        <th>Verdict</th>
                                        <th>Inspected</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_lots as $lot): ?>
                                        <tr>
                                            <td class="fw-medium">#<?= $lot['lot_inspection_id'] ?></td>
                                            <td><a href="/dashboards/admin/order_details.php?order_id=<?= $lot['order_id'] ?>">#<?= $lot['order_id'] ?></a></td>
                                            <td><?= htmlspecialchars($lot['inspector_name']) ?></td>
                                            <td><?= $lot['lot_size'] ?> / <?= $lot['sample_size'] ?></td>
                                            <td><?= $lot['aql_level'] ?></td>
                                            <td><?= $lot['critical_defects'] ?> / <?= $lot['major_defects'] ?> / <?= $lot['minor_defects'] ?></td>
                                            <td>
                                                <?php if ($lot['verdict'] === 'Passed'): ?>
                                                    <span class="aql-badge aql-pass"><i class="fas fa-check me-1"></i>Passed</span>
                                                <?php elseif ($lot['verdict'] === 'Failed'): ?>
                                                    <span class="aql-badge aql-fail"><i class="fas fa-times me-1"></i>Failed</span>
                                                <?php else: ?>
                                                    <span class="aql-badge aql-pending"><i class="fas fa-clock me-1"></i>Pending</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-muted small"><?= $lot['inspected_at'] ? date('M j, g:i A', strtotime($lot['inspected_at'])) : '—' ?></td>
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
