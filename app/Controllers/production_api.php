<?php
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../config/session_handler.php';
require_once __DIR__ . '/../../config/constants.php';
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
    $details = $pdo->prepare("SELECT order_detail_id FROM order_details WHERE order_id = ?");
    $details->execute([$order_id]);
    while ($d = $details->fetch()) {
        $chk = $pdo->prepare("SELECT track_id FROM garment_tracking WHERE order_detail_id = ?");
        $chk->execute([$d['order_detail_id']]);
        if ($existing = $chk->fetch()) {
            $pdo->prepare("UPDATE garment_tracking SET stage = ?, employee_id = ?, notes = ?, updated_at = NOW() WHERE track_id = ?")
                ->execute([$to_stage, $employee_id, $notes, $existing['track_id']]);
        } else {
            $pdo->prepare("INSERT INTO garment_tracking (order_detail_id, order_id, stage, employee_id, notes) VALUES (?, ?, ?, ?, ?)")
                ->execute([$d['order_detail_id'], $order_id, $to_stage, $employee_id, $notes]);
        }
        $pdo->prepare("INSERT INTO garment_log (order_detail_id, order_id, from_stage, to_stage, employee_id, notes) VALUES (?, ?, ?, ?, ?, ?)")
            ->execute([$d['order_detail_id'], $order_id, $from_stage, $to_stage, $employee_id, $notes]);
    }
}

function getWorkflowOrThrow($pdo, $order_id) {
    $stmt = $pdo->prepare("SELECT ow.*, o.status as order_status, o.payment_status FROM order_workflow ow JOIN orders o ON ow.order_id = o.order_id WHERE ow.order_id = ?");
    $stmt->execute([$order_id]);
    $row = $stmt->fetch();
    if (!$row) throw new Exception('Order not found in workflow');
    return $row;
}

try {
    switch ($action) {

        // ── Stage transition (role-gated) ──
        case 'move_stage':
            $order_id = (int)($_POST['order_id'] ?? 0);
            $new_stage = $_POST['stage'] ?? '';
            if (!$order_id || !$new_stage) throw new Exception('Invalid parameters');

            $wf = getWorkflowOrThrow($pdo, $order_id);
            $from_stage = $wf['stage'];

            // Permission check
            if ($role !== ROLE_ADMIN && !can_transition_stage($role, $from_stage)) {
                throw new Exception('Your role cannot advance orders from this stage');
            }

            // Check valid transition
            $valid_next = get_valid_transitions($role, $from_stage);
            if (!in_array($new_stage, $valid_next, true)) {
                throw new Exception("Cannot transition from '$from_stage' to '$new_stage'");
            }

            $pdo->beginTransaction();

            $pdo->prepare("UPDATE order_workflow SET stage = ?, started_at = COALESCE(started_at, NOW()) WHERE order_id = ?")
                ->execute([$new_stage, $order_id]);

            // Stage-specific side effects
            if ($new_stage === STAGE_REWORK) {
                $reason = $_POST['reason'] ?? 'Moved to rework';
                $ncr_id = !empty($_POST['ncr_id']) ? (int)$_POST['ncr_id'] : null;
                $pdo->prepare("INSERT INTO rework_log (order_id, from_stage, to_stage, reason, triggered_by, ncr_id, created_at) VALUES (?, ?, 'Rework', ?, ?, ?, NOW())")
                    ->execute([$order_id, $from_stage, $reason, $user_id, $ncr_id]);
            }

            if ($new_stage === STAGE_READY_FOR_RELEASE && $role === ROLE_QUALITY_CONTROL_INSPECTOR) {
                $pdo->prepare("UPDATE order_workflow SET completed_at = NOW() WHERE order_id = ?")->execute([$order_id]);
            }

            if ($new_stage === STAGE_RELEASED) {
                $pdo->prepare("UPDATE orders SET status = 'Completed', completion_date = NOW(), released_at = NOW(), released_by = ? WHERE order_id = ?")
                    ->execute([$user_id, $order_id]);
            }

            if (in_array($new_stage, [STAGE_WAITING_MATERIALS], true)) {
                $pdo->prepare("UPDATE orders SET status = 'In Progress' WHERE order_id = ? AND status = 'Pending'")->execute([$order_id]);
            }

            logGarmentTransition($pdo, $order_id, $from_stage, $new_stage, $user_id, $_POST['notes'] ?? '');

            // Save note if provided
            if (!empty($_POST['notes'])) {
                $pdo->prepare("INSERT INTO production_notes (order_id, author_id, content, note_type) VALUES (?, ?, ?, 'general')")
                    ->execute([$order_id, $user_id, $_POST['notes']]);
            }

            $pdo->commit();

            // Notifications
            $notif->create($wf['assigned_employee'] ?: $user_id, "Order #ORD-{$order_id} moved to: {$new_stage}.");
            $ownerStmt = $pdo->prepare("SELECT user_id FROM orders WHERE order_id = ?");
            $ownerStmt->execute([$order_id]);
            $owner = $ownerStmt->fetchColumn();
            if ($owner) {
                $notif->create($owner, "Your order #ORD-{$order_id} status updated to: {$new_stage}.");
            }

            // Notify inventory manager if materials needed
            if ($new_stage === STAGE_WAITING_MATERIALS) {
                $invMgrs = $pdo->prepare("SELECT user_id FROM users WHERE role = 'inventory_manager' AND status = 'Active'");
                $invMgrs->execute();
                foreach ($invMgrs->fetchAll(PDO::FETCH_COLUMN) as $imId) {
                    $notif->create($imId, "Order #ORD-{$order_id} needs material allocation.");
                }
            }

            echo json_encode(['success' => true, 'stage' => $new_stage]);
            break;

        // ── Assign employee ──
        case 'assign_employee':
            if (!in_array($role, [ROLE_ADMIN, ROLE_OPERATIONS_MANAGER], true)) {
                throw new Exception('Only admin or operations manager can assign employees');
            }
            $order_id = (int)($_POST['order_id'] ?? 0);
            $employee_id = (int)($_POST['employee_id'] ?? 0);
            if (!$order_id) throw new Exception('Invalid order');

            $pdo->prepare("UPDATE order_workflow SET assigned_employee = ? WHERE order_id = ?")->execute([$employee_id ?: null, $order_id]);
            $pdo->prepare("UPDATE orders SET employee_id = ? WHERE order_id = ?")->execute([$employee_id ?: null, $order_id]);

            if ($employee_id) {
                $notif->create($employee_id, "You have been assigned to order #ORD-{$order_id}.");
            }
            echo json_encode(['success' => true]);
            break;

        // ── Update priority ──
        case 'set_priority':
            if (!in_array($role, [ROLE_ADMIN, ROLE_OPERATIONS_MANAGER], true)) throw new Exception('Unauthorized');
            $order_id = (int)($_POST['order_id'] ?? 0);
            $priority = $_POST['priority'] ?? '';
            if (!in_array($priority, ['low','medium','high','urgent'])) throw new Exception('Invalid priority');
            $pdo->prepare("UPDATE order_workflow SET priority = ? WHERE order_id = ?")->execute([$priority, $order_id]);
            echo json_encode(['success' => true]);
            break;

        // ── Employee start work (from their assigned task) ──
        case 'employee_start':
            $order_id = (int)($_POST['order_id'] ?? 0);
            $check = $pdo->prepare("SELECT assigned_employee, stage FROM order_workflow WHERE order_id = ?");
            $check->execute([$order_id]);
            $row = $check->fetch();
            if (!$row || $row['assigned_employee'] != $user_id) throw new Exception('Not your task');

            $pdo->prepare("UPDATE order_workflow SET started_at = NOW() WHERE order_id = ?")->execute([$order_id]);
            echo json_encode(['success' => true]);
            break;

        // ── QC submit (production staff finishes → QC) ──
        case 'submit_for_qc':
            $order_id = (int)($_POST['order_id'] ?? 0);
            $notes = $_POST['notes'] ?? '';

            $wf = getWorkflowOrThrow($pdo, $order_id);
            if ($wf['stage'] !== STAGE_FINISHING) throw new Exception('Order must be in Finishing stage to submit for QC');

            if ($role === ROLE_PRODUCTION_STAFF && $wf['assigned_employee'] != $user_id) {
                throw new Exception('Not your task');
            }
            if (!in_array($role, [ROLE_PRODUCTION_STAFF, ROLE_ADMIN, ROLE_OPERATIONS_MANAGER], true)) {
                throw new Exception('Not authorized to submit for QC');
            }

            $pdo->beginTransaction();
            $pdo->prepare("UPDATE order_workflow SET stage = ?, completed_at = NOW() WHERE order_id = ?")
                ->execute([STAGE_QC, $order_id]);
            $pdo->prepare("INSERT INTO production_notes (order_id, author_id, content, note_type) VALUES (?, ?, ?, 'handoff')")
                ->execute([$order_id, $user_id, $notes ?: 'Submitted for quality inspection']);
            logGarmentTransition($pdo, $order_id, STAGE_FINISHING, STAGE_QC, $user_id, $notes ?: 'Submitted for QC');

            $checkIns = $pdo->prepare("SELECT inspection_id FROM qc_inspections WHERE order_id = ?");
            $checkIns->execute([$order_id]);
            if (!$checkIns->fetch()) {
                $pdo->prepare("INSERT INTO qc_inspections (order_id, result) VALUES (?, 'Pending')")->execute([$order_id]);
            }

            $pdo->commit();

            // Notify QC inspectors
            $qcUsers = $pdo->prepare("SELECT user_id FROM users WHERE role = 'quality_control_inspector' AND status = 'Active'");
            $qcUsers->execute();
            foreach ($qcUsers->fetchAll(PDO::FETCH_COLUMN) as $qcId) {
                $notif->create($qcId, "Order #ORD-{$order_id} is ready for QC inspection.");
            }

            echo json_encode(['success' => true, 'message' => 'Submitted for QC']);
            break;

        // ── QC Review ──
        case 'qc_review':
            if (!in_array($role, [ROLE_QUALITY_CONTROL_INSPECTOR, ROLE_ADMIN], true)) {
                throw new Exception('Only QC Inspector can review');
            }
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

            if ($result === 'Passed') {
                $pdo->prepare("UPDATE order_workflow SET stage = ?, completed_at = NOW() WHERE order_id = ?")
                    ->execute([STAGE_READY_FOR_RELEASE, $order_id]);
                logGarmentTransition($pdo, $order_id, STAGE_QC, STAGE_READY_FOR_RELEASE, $user_id, 'QC Passed');
            } else {
                // Create NCR
                $ncrNumber = 'NCR-' . str_pad($order_id, 5, '0', STR_PAD_LEFT) . '-' . date('y') . sprintf('%02d', mt_rand(1, 99));
                $defectType = $_POST['defect_type'] ?? 'major';
                $description = $_POST['failure_reason'] ?? 'Failed QC';
                $pdo->prepare("INSERT INTO ncr_reports (ncr_number, order_id, inspector_id, stage_at_fault, defect_type, description, root_cause, corrective_action, assigned_to, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'open')")
                    ->execute([$ncrNumber, $order_id, $user_id, STAGE_QC, $defectType, $description, $_POST['root_cause'] ?? null, $_POST['corrective_action'] ?? null, $wf['assigned_employee'] ?? null]);
                $ncrId = $pdo->lastInsertId();

                $pdo->prepare("INSERT INTO rework_log (order_id, triggered_by, from_stage, to_stage, reason, notes, ncr_id) VALUES (?, ?, 'QC', 'Rework', ?, ?, ?)")
                    ->execute([$order_id, $user_id, $description, $_POST['required_corrections'] ?? '', $ncrId]);
                $pdo->prepare("UPDATE order_workflow SET stage = ? WHERE order_id = ?")
                    ->execute([STAGE_REWORK, $order_id]);
                logGarmentTransition($pdo, $order_id, STAGE_QC, STAGE_REWORK, $user_id, $description);
            }

            $pdo->commit();

            $empStmt = $pdo->prepare("SELECT assigned_employee FROM order_workflow WHERE order_id = ?");
            $empStmt->execute([$order_id]);
            $assigned = $empStmt->fetchColumn();
            if ($assigned) {
                $msg = $result === 'Passed'
                    ? "Order #ORD-{$order_id} passed QC."
                    : "Order #ORD-{$order_id} failed QC. NCR created.";
                $notif->create($assigned, $msg);
            }

            $ownerStmt = $pdo->prepare("SELECT user_id FROM orders WHERE order_id = ?");
            $ownerStmt->execute([$order_id]);
            $owner = $ownerStmt->fetchColumn();
            if ($owner) {
                $msg = $result === 'Passed'
                    ? "Your order #ORD-{$order_id} passed quality inspection."
                    : "Your order #ORD-{$order_id} needs rework.";
                $notif->create($owner, $msg);
            }

            echo json_encode(['success' => true, 'result' => $result]);
            break;

        // ── Inventory: reserve materials ──
        case 'reserve_materials':
            if (!in_array($role, [ROLE_INVENTORY_MANAGER, ROLE_ADMIN], true)) {
                throw new Exception('Only inventory manager can reserve materials');
            }
            $order_id = (int)($_POST['order_id'] ?? 0);
            $items_json = $_POST['items'] ?? '[]';
            $items = json_decode($items_json, true);
            if (!$order_id || empty($items)) throw new Exception('Invalid parameters');

            $wf = getWorkflowOrThrow($pdo, $order_id);
            if (!in_array($wf['stage'], [STAGE_WAITING_MATERIALS, STAGE_READY_FOR_PRODUCTION], true)) {
                throw new Exception('Order is not waiting for materials');
            }

            $pdo->beginTransaction();
            foreach ($items as $item) {
                $invId = (int)($item['inventory_id'] ?? 0);
                $qty = (float)($item['quantity'] ?? 0);
                if (!$invId || $qty <= 0) continue;

                // Check stock
                $stock = $pdo->prepare("SELECT quantity FROM inventory WHERE inventory_id = ?");
                $stock->execute([$invId]);
                $avail = (float)$stock->fetchColumn();
                if ($avail < $qty) throw new Exception("Insufficient stock for item #{$invId}");

                // Reserve
                $pdo->prepare("INSERT INTO inventory_reservations (order_id, inventory_id, reserved_qty, unit, status, reserved_by) VALUES (?, ?, ?, ?, 'reserved', ?)")
                    ->execute([$order_id, $invId, $qty, $item['unit'] ?? 'piece', $user_id]);

                // Also allocate in order_materials
                $pdo->prepare("INSERT INTO order_materials (order_id, inventory_id, allocated_qty, unit) VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE allocated_qty = allocated_qty + ?")
                    ->execute([$order_id, $invId, $qty, $item['unit'] ?? 'piece', $qty]);
            }

            $pdo->prepare("UPDATE order_workflow SET stage = ? WHERE order_id = ?")
                ->execute([STAGE_MATERIALS_RESERVED, $order_id]);

            $pdo->commit();

            // Notify ops manager
            $opsUsers = $pdo->prepare("SELECT user_id FROM users WHERE role = 'operations_manager' AND status = 'Active'");
            $opsUsers->execute();
            foreach ($opsUsers->fetchAll(PDO::FETCH_COLUMN) as $opsId) {
                $notif->create($opsId, "Materials reserved for order #ORD-{$order_id}.");
            }

            echo json_encode(['success' => true, 'stage' => STAGE_MATERIALS_RESERVED]);
            break;

        // ── Upload progress media ──
        case 'upload_media':
            $order_id = (int)($_POST['order_id'] ?? 0);
            $caption = $_POST['caption'] ?? '';
            $media_type = $_POST['media_type'] ?? 'progress';

            if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('File upload error');
            }

            $uploadDir = __DIR__ . '/../../public/uploads/task_media/';
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
                SELECT o.order_id, o.order_date, o.total_price, o.status, o.payment_status,
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

            foreach ($orders as &$ord) {
                $ord['days_remaining'] = $ord['expected_completion']
                    ? max(0, (strtotime($ord['expected_completion']) - time()) / 86400)
                    : null;
                $ord['is_overdue'] = $ord['expected_completion'] && strtotime($ord['expected_completion']) < time();
                $ord['progress'] = getStageProgress($ord['stage']);

                $fileStmt = $pdo->prepare("SELECT file_path FROM order_files WHERE order_id = ? LIMIT 1");
                $fileStmt->execute([$ord['order_id']]);
                $ord['design_preview'] = $fileStmt->fetchColumn();

                $detStmt = $pdo->prepare("SELECT SUM(quantity) FROM order_details WHERE order_id = ?");
                $detStmt->execute([$ord['order_id']]);
                $ord['total_quantity'] = (int) $detStmt->fetchColumn();

                $ord['stage_quantities'] = [];
                try {
                    $scStmt = $pdo->prepare("SELECT COALESCE(SUM(od.quantity), 0) as qty FROM order_details od WHERE od.order_id = ?");
                    $scStmt->execute([$ord['order_id']]);
                    $totalDet = (int)$scStmt->fetchColumn();
                    $estQty = round($totalDet * ($ord['progress'] / 100));
                    $ord['stage_quantities'][$ord['stage']] = $estQty;
                } catch (Exception $e) {}
            }

            echo json_encode(['orders' => $orders]);
            break;

        // ── Get garment-level tracking ──
        case 'get_garment_tracking':
            $order_id = (int)($_GET['order_id'] ?? 0);
            if (!$order_id) throw new Exception('Order ID required');

            $garments = $pdo->prepare("
                SELECT gt.*, od.size, od.quantity, od.unit_price,
                       u.full_name AS employee_name
                FROM garment_tracking gt
                JOIN order_details od ON gt.order_detail_id = od.order_detail_id
                LEFT JOIN users u ON gt.employee_id = u.user_id
                WHERE gt.order_id = ?
                ORDER BY od.size
            ");
            $garments->execute([$order_id]);

            $history = $pdo->prepare("
                SELECT gl.*, od.size, u.full_name AS employee_name
                FROM garment_log gl
                JOIN order_details od ON gl.order_detail_id = od.order_detail_id
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

        // ── NCR: list for an order ──
        case 'get_ncr':
            $order_id = (int)($_GET['order_id'] ?? 0);
            if (!$order_id) throw new Exception('Order ID required');
            $ncrStmt = $pdo->prepare("
                SELECT n.*, u.full_name AS inspector_name
                FROM ncr_reports n
                LEFT JOIN users u ON n.inspector_id = u.user_id
                WHERE n.order_id = ?
                ORDER BY n.created_at DESC
            ");
            $ncrStmt->execute([$order_id]);
            echo json_encode(['ncrs' => $ncrStmt->fetchAll()]);
            break;

        // ── NCR: update status ──
        case 'update_ncr':
            if (!in_array($role, [ROLE_QUALITY_CONTROL_INSPECTOR, ROLE_ADMIN, ROLE_OPERATIONS_MANAGER], true)) {
                throw new Exception('Unauthorized');
            }
            $ncr_id = (int)($_POST['ncr_id'] ?? 0);
            $status = $_POST['status'] ?? '';
            if (!in_array($status, ['open','in_progress','resolved','closed'])) throw new Exception('Invalid status');
            $pdo->prepare("UPDATE ncr_reports SET status = ?, resolved_at = IF(? IN ('resolved','closed'), NOW(), NULL) WHERE ncr_id = ?")
                ->execute([$status, $status, $ncr_id]);
            echo json_encode(['success' => true]);
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
