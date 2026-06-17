<?php
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../config/session_handler.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/NotificationController.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$role = get_user_role();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

$notif = new NotificationController($pdo);

function logGarmentTransition($pdo, $order_id, $from_stage, $to_stage, $employee_id, $notes = '') {
    // Update or insert garment_tracking for each order_detail
    $details = $pdo->prepare("SELECT detail_id FROM order_details WHERE order_id = ?");
    $details->execute([$order_id]);
    while ($d = $details->fetch()) {
        // Upsert current stage
        $chk = $pdo->prepare("SELECT track_id FROM garment_tracking WHERE order_detail_id = ?");
        $chk->execute([$d['detail_id']]);
        if ($existing = $chk->fetch()) {
            $pdo->prepare("UPDATE garment_tracking SET stage = ?, employee_id = ?, notes = ?, updated_at = NOW() WHERE track_id = ?")
                ->execute([$to_stage, $employee_id, $notes, $existing['track_id']]);
        } else {
            $pdo->prepare("INSERT INTO garment_tracking (order_detail_id, order_id, stage, employee_id, notes) VALUES (?, ?, ?, ?, ?)")
                ->execute([$d['detail_id'], $order_id, $to_stage, $employee_id, $notes]);
        }
        // Log history
        $pdo->prepare("INSERT INTO garment_log (order_detail_id, order_id, from_stage, to_stage, employee_id, notes) VALUES (?, ?, ?, ?, ?, ?)")
            ->execute([$d['detail_id'], $order_id, $from_stage, $to_stage, $employee_id, $notes]);
    }
}

try {
    switch ($action) {

        // ── Kanban: move order to new stage ──
        case 'move_stage':
            if ($role !== ROLE_ADMIN) {
                throw new Exception('Only admins can move stages');
            }
            $order_id = (int)($_POST['order_id'] ?? 0);
            $new_stage = $_POST['stage'] ?? '';
            $valid_stages = [
                STAGE_ORDER_RECEIVED, STAGE_DESIGN_REVIEW, STAGE_MATERIAL_PREP,
                STAGE_CUTTING, STAGE_PRINTING, STAGE_SEWING,
                STAGE_QUALITY_INSPECTION, STAGE_REWORK, STAGE_PACKAGING,
                STAGE_READY_PICKUP, STAGE_COMPLETED,
            ];
            if (!$order_id || !in_array($new_stage, $valid_stages)) {
                throw new Exception('Invalid parameters');
            }

            // Validate assigned employee can work on target stage
            $assigned = $pdo->prepare("SELECT assigned_employee, stage FROM order_workflow WHERE order_id = ?");
            $assigned->execute([$order_id]);
            $wf = $assigned->fetch();
            if ($wf && $wf['assigned_employee']) {
                $empPos = $pdo->prepare("SELECT position_id FROM employees WHERE user_id = ?");
                $empPos->execute([$wf['assigned_employee']]);
                $posId = $empPos->fetchColumn();
                if ($posId) {
                    $allowed = getPositionStages($posId);
                    if (!in_array($new_stage, $allowed) && !in_array($new_stage, [STAGE_COMPLETED, STAGE_READY_PICKUP])) {
                        throw new Exception("Assigned employee's position does not allow stage: $new_stage");
                    }
                }
            }

            $pdo->beginTransaction();

            $from_stage = $wf['stage'] ?: 'Unknown';

            // Update order workflow stage
            $stmt = $pdo->prepare("UPDATE order_workflow SET stage = ?, completed_at = IF(? IN ('Completed','Ready for Pickup','Quality Inspection'), NOW(), NULL) WHERE order_id = ?");
            $stmt->execute([$new_stage, $new_stage, $order_id]);

            // If moved to Completed, also update order status
            if ($new_stage === STAGE_COMPLETED) {
                $pdo->prepare("UPDATE orders SET status = 'Completed', completion_date = NOW() WHERE order_id = ?")->execute([$order_id]);
            }

            // If moved to Rework, log it
            if ($new_stage === STAGE_REWORK) {
                $prev = $_POST['from_stage'] ?? $from_stage;
                $reason = $_POST['reason'] ?? '';
                $pdo->prepare("INSERT INTO rework_log (order_id, from_stage, to_stage, reason, triggered_by, created_at) VALUES (?, ?, 'Rework', ?, ?, NOW())")
                    ->execute([$order_id, $prev, $reason, $user_id]);
            }

            // Log garment tracking
            logGarmentTransition($pdo, $order_id, $from_stage, $new_stage, $user_id, $_POST['reason'] ?? '');

            $pdo->commit();

            // Notify the assigned employee
            $empStmt = $pdo->prepare("SELECT assigned_employee FROM order_workflow WHERE order_id = ?");
            $empStmt->execute([$order_id]);
            $assigned = $empStmt->fetchColumn();
            if ($assigned) {
                $notif->create($assigned, "Order #ORD-{$order_id} moved to stage: {$new_stage}.");
            }

            echo json_encode(['success' => true, 'stage' => $new_stage]);
            break;

        // ── Assign employee to order ──
        case 'assign_employee':
            if ($role !== ROLE_ADMIN) {
                throw new Exception('Only admins can assign employees');
            }
            $order_id = (int)($_POST['order_id'] ?? 0);
            $employee_id = (int)($_POST['employee_id'] ?? 0);
            if (!$order_id) throw new Exception('Invalid order');

            // Validate employee position matches current stage
            if ($employee_id) {
                $cur = $pdo->prepare("SELECT stage FROM order_workflow WHERE order_id = ?");
                $cur->execute([$order_id]);
                $curStage = $cur->fetchColumn();
                $empPos = $pdo->prepare("SELECT position_id FROM employees WHERE user_id = ?");
                $empPos->execute([$employee_id]);
                $posId = $empPos->fetchColumn();
                if ($posId && $curStage) {
                    $allowed = getPositionStages($posId);
                    if (!in_array($curStage, $allowed) && !in_array($curStage, [STAGE_COMPLETED, STAGE_READY_PICKUP, STAGE_QUALITY_INSPECTION])) {
                        throw new Exception("Employee position does not allow current stage: $curStage");
                    }
                }
            }

            $pdo->prepare("UPDATE order_workflow SET assigned_employee = ? WHERE order_id = ?")->execute([$employee_id ?: null, $order_id]);
            $pdo->prepare("UPDATE orders SET employee_id = ? WHERE order_id = ?")->execute([$employee_id ?: null, $order_id]);

            if ($employee_id) {
                $notif->create($employee_id, "You have been assigned to order #ORD-{$order_id}.");
            }
            echo json_encode(['success' => true]);
            break;

        // ── Update priority ──
        case 'set_priority':
            if ($role !== ROLE_ADMIN) throw new Exception('Unauthorized');
            $order_id = (int)($_POST['order_id'] ?? 0);
            $priority = $_POST['priority'] ?? '';
            if (!in_array($priority, ['low','medium','high','urgent'])) throw new Exception('Invalid priority');
            $pdo->prepare("UPDATE order_workflow SET priority = ? WHERE order_id = ?")->execute([$priority, $order_id]);
            echo json_encode(['success' => true]);
            break;

        // ── Employee: start / pause / update stage ──
        case 'employee_update_stage':
            $order_id = (int)($_POST['order_id'] ?? 0);
            $new_stage = $_POST['stage'] ?? '';
            $notes = $_POST['notes'] ?? '';

            // Verify ownership
            $check = $pdo->prepare("SELECT assigned_employee, stage FROM order_workflow WHERE order_id = ?");
            $check->execute([$order_id]);
            $row = $check->fetch();
            if (!$row || $row['assigned_employee'] != $user_id) throw new Exception('Not your task');
            $from_stage = $row['stage'];

            $pdo->beginTransaction();

            $pdo->prepare("UPDATE order_workflow SET stage = ?, workflow_notes = ?, started_at = COALESCE(started_at, NOW()) WHERE order_id = ?")
                ->execute([$new_stage, $notes, $order_id]);

            // Also update order status
            if ($new_stage === STAGE_QUALITY_INSPECTION) {
                $pdo->prepare("UPDATE orders SET status = 'In Progress' WHERE order_id = ?")->execute([$order_id]);
            }

            // Log garment tracking
            logGarmentTransition($pdo, $order_id, $from_stage, $new_stage, $user_id, $notes);

            // Save note
            if ($notes) {
                $pdo->prepare("INSERT INTO production_notes (order_id, author_id, content, note_type) VALUES (?, ?, ?, 'general')")
                    ->execute([$order_id, $user_id, $notes]);
            }

            $pdo->commit();

            // Notify customer
            $ownerStmt = $pdo->prepare("SELECT user_id FROM orders WHERE order_id = ?");
            $ownerStmt->execute([$order_id]);
            $owner = $ownerStmt->fetchColumn();
            if ($owner) {
                $notif->create($owner, "Your order #ORD-{$order_id} stage updated to: {$new_stage}.");
            }

            echo json_encode(['success' => true, 'stage' => $new_stage]);
            break;

        // ── QC Submission (employee finishes → QC) ──
        case 'submit_for_qc':
            $order_id = (int)($_POST['order_id'] ?? 0);
            $notes = $_POST['notes'] ?? '';

            $check = $pdo->prepare("SELECT assigned_employee, stage FROM order_workflow WHERE order_id = ?");
            $check->execute([$order_id]);
            $row = $check->fetch();
            if (!$row || $row['assigned_employee'] != $user_id) throw new Exception('Not your task');
            $from_stage = $row['stage'];

            $pdo->beginTransaction();
            $pdo->prepare("UPDATE order_workflow SET stage = ?, completed_at = NOW() WHERE order_id = ?")
                ->execute([STAGE_QUALITY_INSPECTION, $order_id]);
            $pdo->prepare("INSERT INTO production_notes (order_id, author_id, content, note_type) VALUES (?, ?, ?, 'handoff')")
                ->execute([$order_id, $user_id, $notes ?: 'Submitted for quality inspection']);

            // Log garment tracking
            logGarmentTransition($pdo, $order_id, $from_stage, STAGE_QUALITY_INSPECTION, $user_id, $notes ?: 'Submitted for QC');

            // Create QC inspection record
            $checkIns = $pdo->prepare("SELECT inspection_id FROM qc_inspections WHERE order_id = ?");
            $checkIns->execute([$order_id]);
            if (!$checkIns->fetch()) {
                $pdo->prepare("INSERT INTO qc_inspections (order_id, result) VALUES (?, 'Pending')")->execute([$order_id]);
            }

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Submitted for QC']);
            break;

        // ── QC Review (admin/employee with QC role) ──
        case 'qc_review':
            $order_id = (int)($_POST['order_id'] ?? 0);
            $result = $_POST['result'] ?? '';
            if (!in_array($result, ['Passed','Failed'])) throw new Exception('Invalid result');

            $checklist = [
                'design_accuracy' => (int)($_POST['design_accuracy'] ?? 0),
                'print_alignment' => (int)($_POST['print_alignment'] ?? 0),
                'embroidery_quality' => (int)($_POST['embroidery_quality'] ?? 0),
                'stitching_quality' => (int)($_POST['stitching_quality'] ?? 0),
                'size_accuracy' => (int)($_POST['size_accuracy'] ?? 0),
                'fabric_condition' => (int)($_POST['fabric_condition'] ?? 0),
                'cleanliness' => (int)($_POST['cleanliness'] ?? 0),
                'packaging_readiness' => (int)($_POST['packaging_readiness'] ?? 0),
            ];

            $pdo->beginTransaction();

            // Upsert inspection record
            $insStmt = $pdo->prepare("SELECT inspection_id FROM qc_inspections WHERE order_id = ?");
            $insStmt->execute([$order_id]);
            $existing = $insStmt->fetch();

            if ($existing) {
                $updateCols = [];
                $updateParams = [];
                foreach ($checklist as $col => $val) {
                    $updateCols[] = "{$col} = ?";
                    $updateParams[] = $val;
                }
                $updateCols[] = "result = ?";
                $updateParams[] = $result;
                $updateCols[] = "inspector_id = ?";
                $updateParams[] = $user_id;
                $updateCols[] = "inspected_at = NOW()";
                $updateCols[] = "failure_reason = ?";
                $updateParams[] = $_POST['failure_reason'] ?? null;
                $updateCols[] = "feedback = ?";
                $updateParams[] = $_POST['feedback'] ?? null;
                $updateCols[] = "required_corrections = ?";
                $updateParams[] = $_POST['required_corrections'] ?? null;
                $updateParams[] = $order_id;

                $pdo->prepare("UPDATE qc_inspections SET " . implode(', ', $updateCols) . " WHERE order_id = ?")
                    ->execute($updateParams);
            } else {
                $checklist['result'] = $result;
                $checklist['inspector_id'] = $user_id;
                $checklist['inspected_at'] = date('Y-m-d H:i:s');
                $checklist['failure_reason'] = $_POST['failure_reason'] ?? null;
                $checklist['feedback'] = $_POST['feedback'] ?? null;
                $checklist['required_corrections'] = $_POST['required_corrections'] ?? null;
                $checklist['order_id'] = $order_id;

                $cols = implode(', ', array_keys($checklist));
                $vals = implode(', ', array_fill(0, count($checklist), '?'));
                $pdo->prepare("INSERT INTO qc_inspections ({$cols}) VALUES ({$vals})")
                    ->execute(array_values($checklist));
            }

            // Update workflow stage based on result
            if ($result === 'Passed') {
                $pdo->prepare("UPDATE order_workflow SET stage = ?, completed_at = NOW() WHERE order_id = ?")
                    ->execute([STAGE_PACKAGING, $order_id]);
                logGarmentTransition($pdo, $order_id, STAGE_QUALITY_INSPECTION, STAGE_PACKAGING, $user_id, 'QC Passed');
            } else {
                $pdo->prepare("INSERT INTO rework_log (order_id, triggered_by, from_stage, to_stage, reason, notes) VALUES (?, ?, 'Quality Inspection', 'Rework', ?, ?)")
                    ->execute([$order_id, $user_id, $_POST['failure_reason'] ?? 'Failed QC', $_POST['required_corrections'] ?? '']);
                $pdo->prepare("UPDATE order_workflow SET stage = ? WHERE order_id = ?")
                    ->execute([STAGE_REWORK, $order_id]);
                logGarmentTransition($pdo, $order_id, STAGE_QUALITY_INSPECTION, STAGE_REWORK, $user_id, $_POST['failure_reason'] ?? 'QC Failed');
            }

            $pdo->commit();

            // Notify assigned employee
            $empStmt = $pdo->prepare("SELECT assigned_employee FROM order_workflow WHERE order_id = ?");
            $empStmt->execute([$order_id]);
            $assigned = $empStmt->fetchColumn();
            if ($assigned) {
                $msg = $result === 'Passed'
                    ? "Your order #ORD-{$order_id} passed QC and moved to Packaging."
                    : "Your order #ORD-{$order_id} failed QC. Please check feedback.";
                $notif->create($assigned, $msg);
            }

            // Notify customer
            $ownerStmt = $pdo->prepare("SELECT user_id FROM orders WHERE order_id = ?");
            $ownerStmt->execute([$order_id]);
            $owner = $ownerStmt->fetchColumn();
            if ($owner) {
                $msg = $result === 'Passed'
                    ? "Your order #ORD-{$order_id} passed quality inspection."
                    : "Your order #ORD-{$order_id} needs rework. We'll update you.";
                $notif->create($owner, $msg);
            }

            echo json_encode(['success' => true, 'result' => $result]);
            break;

        // ── Upload progress media ──
        case 'upload_media':
            $order_id = (int)($_POST['order_id'] ?? 0);
            $caption = $_POST['caption'] ?? '';
            $media_type = $_POST['media_type'] ?? 'progress';

            if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('File upload error');
            }

            $uploadDir = __DIR__ . '/../public/uploads/task_media/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

            $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
            $filename = 'media_' . $order_id . '_' . time() . '.' . $ext;
            move_uploaded_file($_FILES['file']['tmp_name'], $uploadDir . $filename);

            $pdo->prepare("INSERT INTO task_media (order_id, employee_id, file_path, caption, media_type) VALUES (?, ?, ?, ?, ?)")
                ->execute([$order_id, $user_id, 'uploads/task_media/' . $filename, $caption, $media_type]);

            echo json_encode(['success' => true, 'file' => $filename]);
            break;

        // ── Add production note ──
        case 'add_note':
            $order_id = (int)($_POST['order_id'] ?? 0);
            $content = trim($_POST['content'] ?? '');
            $note_type = $_POST['note_type'] ?? 'general';
            if (!$content) throw new Exception('Note cannot be empty');
            $pdo->prepare("INSERT INTO production_notes (order_id, author_id, content, note_type) VALUES (?, ?, ?, ?)")
                ->execute([$order_id, $user_id, $content, $note_type]);
            echo json_encode(['success' => true]);
            break;

        // ── Fetch board data (for kanban) ──
        case 'get_board':
            $stage_filter = $_GET['stage'] ?? '';
            $sql = "
                SELECT o.order_id, o.order_date, o.total_price, o.status,
                       ow.stage, ow.priority, ow.assigned_employee, ow.expected_completion,
                       ow.product_type, ow.started_at,
                       u.full_name AS customer_name,
                       e.full_name AS employee_name,
                       s.service_name
                FROM orders o
                JOIN users u ON o.user_id = u.user_id
                JOIN services s ON o.service_id = s.service_id
                LEFT JOIN order_workflow ow ON o.order_id = ow.order_id
                LEFT JOIN users e ON ow.assigned_employee = e.user_id
                WHERE o.status NOT IN ('Cancelled', 'Refunded')
            ";
            $params = [];
            if ($stage_filter) {
                $sql .= " AND ow.stage = ?";
                $params[] = $stage_filter;
            }
            $sql .= " ORDER BY 
                CASE ow.priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END,
                ow.expected_completion ASC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $orders = $stmt->fetchAll();

            // Enrich order data
            foreach ($orders as &$ord) {
                $ord['days_remaining'] = $ord['expected_completion']
                    ? max(0, (strtotime($ord['expected_completion']) - time()) / 86400)
                    : null;
                $ord['is_overdue'] = $ord['expected_completion'] && strtotime($ord['expected_completion']) < time();
                $ord['progress'] = getStageProgress($ord['stage']);

                // Get design preview
                $fileStmt = $pdo->prepare("SELECT file_path FROM order_files WHERE order_id = ? LIMIT 1");
                $fileStmt->execute([$ord['order_id']]);
                $ord['design_preview'] = $fileStmt->fetchColumn();

                // Total item count
                $detStmt = $pdo->prepare("SELECT SUM(quantity) FROM order_details WHERE order_id = ?");
                $detStmt->execute([$ord['order_id']]);
                $ord['total_quantity'] = (int) $detStmt->fetchColumn();

                // Batch progress: estimate items completed through this stage
                $ord['stage_quantities'] = [];
                try {
                    $scStmt = $pdo->prepare("
                        SELECT COALESCE(SUM(od.quantity), 0) as qty
                        FROM order_details od
                        WHERE od.order_id = ?
                    ");
                    $scStmt->execute([$ord['order_id']]);
                    $totalDet = (int)$scStmt->fetchColumn();
                    // Estimate items at this stage as total * stage_progress / 100 (approximate)
                    $estQty = round($totalDet * ($ord['progress'] / 100));
                    $ord['stage_quantities'][$ord['stage']] = $estQty;
                } catch (Exception $e) {
                    // Silently fail if garment_tracking doesn't exist
                }
            }

            echo json_encode(['orders' => $orders]);
            break;

        // ── Get garment-level tracking for an order ──
        case 'get_garment_tracking':
            $order_id = (int)($_GET['order_id'] ?? 0);
            if (!$order_id) throw new Exception('Order ID required');

            $garments = $pdo->prepare("
                SELECT gt.*, od.size, od.quantity, od.unit_price,
                       u.full_name AS employee_name
                FROM garment_tracking gt
                JOIN order_details od ON gt.order_detail_id = od.detail_id
                LEFT JOIN users u ON gt.employee_id = u.user_id
                WHERE gt.order_id = ?
                ORDER BY od.size
            ");
            $garments->execute([$order_id]);

            $history = $pdo->prepare("
                SELECT gl.*, od.size, u.full_name AS employee_name
                FROM garment_log gl
                JOIN order_details od ON gl.order_detail_id = od.detail_id
                LEFT JOIN users u ON gl.employee_id = u.user_id
                WHERE gl.order_id = ?
                ORDER BY gl.created_at DESC LIMIT 50
            ");
            $history->execute([$order_id]);

            echo json_encode([
                'garments' => $garments->fetchAll(),
                'history' => $history->fetchAll(),
            ]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Unknown action']);
    }
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
