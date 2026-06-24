<?php

// ── Roles ──
define('ROLE_ADMIN', 'admin');
define('ROLE_CUSTOMER', 'customer');
define('ROLE_OPERATIONS_MANAGER', 'operations_manager');
define('ROLE_PRODUCTION_STAFF', 'production_staff');
define('ROLE_INVENTORY_MANAGER', 'inventory_manager');
define('ROLE_QUALITY_CONTROL_INSPECTOR', 'quality_control_inspector');

// Legacy roles kept for backwards compatibility while data is migrated
define('ROLE_MANAGER', 'manager');
define('ROLE_EMPLOYEE', 'employee');

// ── User Status ──
define('STATUS_ACTIVE', 'Active');
define('STATUS_INACTIVE', 'Inactive');
define('STATUS_SUSPENDED', 'Suspended');

// ── Order Status ──
define('ORDER_PENDING', 'Pending');
define('ORDER_IN_PROGRESS', 'In Progress');
define('ORDER_COMPLETED', 'Completed');
define('ORDER_CANCELLED', 'Cancelled');
define('ORDER_REFUNDED', 'Refunded');

// ── Payment Status ──
define('PAYMENT_PENDING', 'Pending');
define('PAYMENT_PAID', 'Paid');
define('PAYMENT_REFUNDED', 'Refunded');

// ── Production Workflow Stages (New Lifecycle) ──
// Admin        → any stage (override)
// Ops Manager  → Pending Verification, Customer Action Required, Ready for Production, Ready for Release
// Customer     → Customer Action Required (resubmit)
// Inventory    → Waiting for Materials, Materials Reserved
// Production   → Cutting, Sewing, Embroidery, Finishing, Rework
// QC Inspector → QC

define('STAGE_PENDING_VERIFICATION',  'Pending Verification');
define('STAGE_CUSTOMER_ACTION',       'Customer Action Required');
define('STAGE_READY_FOR_PRODUCTION',  'Ready for Production');
define('STAGE_WAITING_MATERIALS',     'Waiting for Materials');
define('STAGE_MATERIALS_RESERVED',    'Materials Reserved');
define('STAGE_CUTTING',               'Cutting');
define('STAGE_SEWING',                'Sewing');
define('STAGE_EMBROIDERY',            'Embroidery');
define('STAGE_FINISHING',             'Finishing');
define('STAGE_QC',                    'QC');
define('STAGE_REWORK',                'Rework');
define('STAGE_READY_FOR_RELEASE',     'Ready for Release');
define('STAGE_AWAITING_PAYMENT',      'Awaiting Final Payment');
define('STAGE_RELEASED',              'Released');

// Legacy stage aliases (still referenced in old data/view files)
define('STAGE_ORDER_RECEIVED',       STAGE_PENDING_VERIFICATION);
define('STAGE_DESIGN_REVIEW',        STAGE_READY_FOR_PRODUCTION);
define('STAGE_MATERIAL_PREP',        STAGE_WAITING_MATERIALS);
define('STAGE_SAMPLE_REVIEW',        STAGE_READY_FOR_PRODUCTION);
define('STAGE_BULK_PRODUCTION',      STAGE_FINISHING);
define('STAGE_QUALITY_INSPECTION',   STAGE_QC);
define('STAGE_PRINTING',             STAGE_EMBROIDERY);
define('STAGE_PACKAGING',            STAGE_READY_FOR_RELEASE);
define('STAGE_READY_PICKUP',         STAGE_READY_FOR_RELEASE);
define('STAGE_COMPLETED',            STAGE_RELEASED);

// ── Customer-facing simplified stages ──
define('CSTAGE_CONFIRMED',    'Order Confirmed');
define('CSTAGE_PRODUCTION',   'In Production');
define('CSTAGE_QUALITY',      'Quality Check');
define('CSTAGE_PACKAGING_C',  'Packaging');
define('CSTAGE_READY',        'Ready for Pickup');
define('CSTAGE_DONE',         'Completed');

// ── All stages in flow order ──
$ORDER_STAGES = [
    STAGE_PENDING_VERIFICATION,
    STAGE_CUSTOMER_ACTION,
    STAGE_READY_FOR_PRODUCTION,
    STAGE_WAITING_MATERIALS,
    STAGE_MATERIALS_RESERVED,
    STAGE_CUTTING,
    STAGE_SEWING,
    STAGE_EMBROIDERY,
    STAGE_FINISHING,
    STAGE_QC,
    STAGE_REWORK,
    STAGE_READY_FOR_RELEASE,
    STAGE_AWAITING_PAYMENT,
    STAGE_RELEASED,
];

// ── Stage display config for kanban ──
$STAGE_CONFIG = [
    STAGE_PENDING_VERIFICATION => ['label' => 'Pending Verification',  'color' => '#f59e0b', 'icon' => 'fas fa-inbox'],
    STAGE_CUSTOMER_ACTION      => ['label' => 'Customer Action',       'color' => '#f97316', 'icon' => 'fas fa-user-edit'],
    STAGE_READY_FOR_PRODUCTION => ['label' => 'Ready for Production',  'color' => '#b91c1c', 'icon' => 'fas fa-check-double'],
    STAGE_WAITING_MATERIALS    => ['label' => 'Waiting for Materials', 'color' => '#8b5cf6', 'icon' => 'fas fa-hourglass-half'],
    STAGE_MATERIALS_RESERVED   => ['label' => 'Materials Reserved',    'color' => '#06b6d4', 'icon' => 'fas fa-warehouse'],
    STAGE_CUTTING              => ['label' => 'Cutting',               'color' => '#06b6d4', 'icon' => 'fas fa-cut'],
    STAGE_SEWING               => ['label' => 'Sewing',                'color' => '#f97316', 'icon' => 'fas fa-tshirt'],
    STAGE_EMBROIDERY           => ['label' => 'Embroidery',            'color' => '#ec4899', 'icon' => 'fas fa-print'],
    STAGE_FINISHING            => ['label' => 'Finishing',             'color' => '#14b8a6', 'icon' => 'fas fa-iron'],
    STAGE_QC                   => ['label' => 'QC',                    'color' => '#10b981', 'icon' => 'fas fa-search'],
    STAGE_REWORK               => ['label' => 'Rework',                'color' => '#ef4444', 'icon' => 'fas fa-undo-alt'],
    STAGE_READY_FOR_RELEASE    => ['label' => 'Ready for Release',     'color' => '#6366f1', 'icon' => 'fas fa-check-circle'],
    STAGE_AWAITING_PAYMENT     => ['label' => 'Awaiting Payment',      'color' => '#7c3aed', 'icon' => 'fas fa-credit-card'],
    STAGE_RELEASED             => ['label' => 'Released',              'color' => '#10b981', 'icon' => 'fas fa-check-double'],
];

// ── Stage ownership (which roles can advance FROM each stage) ──
// Each entry lists roles allowed to move an order forward from that stage.
// Admin is always allowed implicitly.
$STAGE_OWNERSHIP = [
    STAGE_PENDING_VERIFICATION => [ROLE_OPERATIONS_MANAGER],
    STAGE_CUSTOMER_ACTION      => [ROLE_OPERATIONS_MANAGER, ROLE_CUSTOMER],
    STAGE_READY_FOR_PRODUCTION => [ROLE_OPERATIONS_MANAGER, ROLE_INVENTORY_MANAGER],
    STAGE_WAITING_MATERIALS    => [ROLE_INVENTORY_MANAGER],
    STAGE_MATERIALS_RESERVED   => [ROLE_INVENTORY_MANAGER, ROLE_OPERATIONS_MANAGER],
    STAGE_CUTTING              => [ROLE_PRODUCTION_STAFF],
    STAGE_SEWING               => [ROLE_PRODUCTION_STAFF],
    STAGE_EMBROIDERY           => [ROLE_PRODUCTION_STAFF],
    STAGE_FINISHING            => [ROLE_PRODUCTION_STAFF],
    STAGE_QC                   => [ROLE_QUALITY_CONTROL_INSPECTOR],
    STAGE_REWORK               => [ROLE_PRODUCTION_STAFF],
    STAGE_READY_FOR_RELEASE    => [ROLE_OPERATIONS_MANAGER],
    STAGE_AWAITING_PAYMENT     => [ROLE_CUSTOMER, ROLE_ADMIN],
    STAGE_RELEASED             => [],
];

// ── Valid transitions per stage ──
$STAGE_TRANSITIONS = [
    STAGE_PENDING_VERIFICATION => [STAGE_CUSTOMER_ACTION, STAGE_READY_FOR_PRODUCTION],
    STAGE_CUSTOMER_ACTION      => [STAGE_READY_FOR_PRODUCTION],
    STAGE_READY_FOR_PRODUCTION => [STAGE_WAITING_MATERIALS, STAGE_MATERIALS_RESERVED],
    STAGE_WAITING_MATERIALS    => [STAGE_MATERIALS_RESERVED],
    STAGE_MATERIALS_RESERVED   => [STAGE_CUTTING],
    STAGE_CUTTING              => [STAGE_SEWING],
    STAGE_SEWING               => [STAGE_EMBROIDERY],
    STAGE_EMBROIDERY           => [STAGE_FINISHING],
    STAGE_FINISHING            => [STAGE_QC],
    STAGE_QC                   => [STAGE_REWORK, STAGE_READY_FOR_RELEASE],
    STAGE_REWORK               => [STAGE_QC],
    STAGE_READY_FOR_RELEASE    => [STAGE_AWAITING_PAYMENT, STAGE_RELEASED],
    STAGE_AWAITING_PAYMENT     => [STAGE_RELEASED],
    STAGE_RELEASED             => [],
];

// ── Customer stage mapping (internal → customer-facing) ──
$CUSTOMER_STAGE_MAP = [
    STAGE_PENDING_VERIFICATION => 'Order Confirmed',
    STAGE_CUSTOMER_ACTION      => 'Action Needed',
    STAGE_READY_FOR_PRODUCTION => 'In Production',
    STAGE_WAITING_MATERIALS    => 'In Production',
    STAGE_MATERIALS_RESERVED   => 'In Production',
    STAGE_CUTTING              => 'In Production',
    STAGE_SEWING               => 'In Production',
    STAGE_EMBROIDERY           => 'In Production',
    STAGE_FINISHING            => 'In Production',
    STAGE_QC                   => 'Quality Check',
    STAGE_REWORK               => 'In Production',
    STAGE_READY_FOR_RELEASE    => 'Ready for Release',
    STAGE_AWAITING_PAYMENT     => 'Awaiting Payment',
    STAGE_RELEASED             => 'Completed',
];

// ── Stage progress percentages ──
function getStageProgress($stage)
{
    $map = [
        STAGE_PENDING_VERIFICATION => 5,
        STAGE_CUSTOMER_ACTION      => 10,
        STAGE_READY_FOR_PRODUCTION => 15,
        STAGE_WAITING_MATERIALS    => 20,
        STAGE_MATERIALS_RESERVED   => 25,
        STAGE_CUTTING              => 40,
        STAGE_SEWING               => 55,
        STAGE_EMBROIDERY           => 65,
        STAGE_FINISHING            => 75,
        STAGE_QC                   => 85,
        STAGE_REWORK               => 50,
        STAGE_READY_FOR_RELEASE    => 92,
        STAGE_AWAITING_PAYMENT     => 95,
        STAGE_RELEASED             => 100,
    ];
    return $map[$stage] ?? 0;
}

// ── Permission helpers ──

/**
 * Check if a role is allowed to move an order from a given stage.
 * Admin is always allowed.
 */
function can_transition_stage(string $role, string $currentStage): bool {
    if ($role === ROLE_ADMIN) return true;
    global $STAGE_OWNERSHIP;
    $owners = $STAGE_OWNERSHIP[$currentStage] ?? [];
    return in_array($role, $owners, true);
}

/**
 * Get valid next stages for a role from a given stage.
 */
function get_valid_transitions(string $role, string $currentStage): array {
    if ($role === ROLE_ADMIN) {
        global $STAGE_TRANSITIONS;
        return $STAGE_TRANSITIONS[$currentStage] ?? [];
    }
    if (!can_transition_stage($role, $currentStage)) return [];
    global $STAGE_TRANSITIONS;
    return $STAGE_TRANSITIONS[$currentStage] ?? [];
}

/**
 * Get which role is supposed to handle a stage (for UI hints).
 */
function get_stage_owner_role(string $stage): string {
    global $STAGE_OWNERSHIP;
    $owners = $STAGE_OWNERSHIP[$stage] ?? [];
    return $owners[0] ?? ROLE_ADMIN;
}

/**
 * Get stages a given role is allowed to transition.
 */
function get_role_allowed_stages(string $role): array {
    if ($role === ROLE_ADMIN) {
        global $ORDER_STAGES;
        return $ORDER_STAGES;
    }
    global $STAGE_OWNERSHIP;
    $stages = [];
    foreach ($STAGE_OWNERSHIP as $stage => $owners) {
        if (in_array($role, $owners, true)) {
            $stages[] = $stage;
        }
    }
    return $stages;
}

// ── Backwards compatibility shims for old position-based helpers ──
function getPositionStages($position_id) {
    global $ORDER_STAGES;
    return $ORDER_STAGES;
}

function getEmployeePosition($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT e.position_id, p.position_name FROM employees e JOIN positions p ON e.position_id = p.position_id WHERE e.user_id = ?");
    $stmt->execute([$user_id]);
    $res = $stmt->fetch();
    if (!$res) return ['position_id' => 0, 'position_name' => ''];
    return $res;
}

// AQL Sampling Levels
define('AQL_LEVEL_1', '1.0');
define('AQL_LEVEL_2', '2.5');
define('AQL_LEVEL_3', '4.0');
define('AQL_INSPECTION_NORMAL', 'I');
define('AQL_INSPECTION_TIGHTENED', 'II');
define('AQL_INSPECTION_REDUCED', 'III');

function getAQLSampleSize($lot_size, $inspection_level = 'II') {
    $code_table = [
        [2, 8, 'A', 'A', 'A'],
        [9, 15, 'A', 'A', 'B'],
        [16, 25, 'B', 'C', 'D'],
        [26, 50, 'C', 'D', 'E'],
        [51, 90, 'C', 'E', 'F'],
        [91, 150, 'D', 'F', 'G'],
        [151, 280, 'E', 'G', 'H'],
        [281, 500, 'F', 'H', 'J'],
        [501, 1200, 'G', 'J', 'K'],
        [1201, 3200, 'H', 'K', 'L'],
        [3201, 10000, 'J', 'L', 'M'],
        [10001, 35000, 'K', 'M', 'N'],
        [35001, 150000, 'L', 'N', 'P'],
    ];
    $level_map = ['I' => 2, 'II' => 3, 'III' => 4];
    $col = $level_map[$inspection_level] ?? 3;
    $code = 'A';
    foreach ($code_table as $row) {
        if ($lot_size >= $row[0] && $lot_size <= $row[1]) {
            $code = $row[$col - 1];
            break;
        }
    }
    // Master sample size table: code => sample size
    $sample_sizes = [
        'A' => 2, 'B' => 3, 'C' => 5, 'D' => 8,
        'E' => 13, 'F' => 20, 'G' => 32, 'H' => 50,
        'J' => 80, 'K' => 125, 'L' => 200, 'M' => 315,
        'N' => 500, 'P' => 800,
    ];
    return $sample_sizes[$code] ?? min($lot_size, 500);
}

function getAQLAcceptReject($aql_level, $sample_size) {
    // AQL 1.0: Accept/Reject (Ac, Re) for Major defects
    // AQL 2.5: Accept/Reject
    // AQL 4.0: Accept/Reject
    $table = [
        '1.0' => [
            2 => [0, 1], 3 => [0, 1], 5 => [0, 1], 8 => [0, 1],
            13 => [0, 1], 20 => [0, 1], 32 => [1, 2], 50 => [1, 2],
            80 => [2, 3], 125 => [3, 4], 200 => [5, 6], 315 => [7, 8],
            500 => [10, 11], 800 => [14, 15],
        ],
        '2.5' => [
            2 => [0, 1], 3 => [0, 1], 5 => [0, 1], 8 => [1, 2],
            13 => [1, 2], 20 => [2, 3], 32 => [3, 4], 50 => [5, 6],
            80 => [7, 8], 125 => [10, 11], 200 => [14, 15], 315 => [21, 22],
            500 => [21, 22], 800 => [21, 22],
        ],
        '4.0' => [
            2 => [0, 1], 3 => [0, 1], 5 => [1, 2], 8 => [1, 2],
            13 => [2, 3], 20 => [3, 4], 32 => [5, 6], 50 => [7, 8],
            80 => [10, 11], 125 => [14, 15], 200 => [21, 22], 315 => [21, 22],
            500 => [21, 22], 800 => [21, 22],
        ],
    ];
    $closest = 2;
    foreach (array_keys($table[$aql_level] ?? $table['2.5']) as $s) {
        if ($s >= $sample_size) { $closest = $s; break; }
        $closest = $s;
    }
    return $table[$aql_level][$closest] ?? [0, 1];
}

function getAQLVerdict($critical, $major, $minor, $aql_level, $sample_size) {
    if ($critical > 0) return 'Failed';
    list($major_ac, $major_re) = getAQLAcceptReject($aql_level, $sample_size);
    // Use AQL 4.0 for minor defects (one level looser)
    list($minor_ac, $minor_re) = getAQLAcceptReject('4.0', $sample_size);
    if ($major >= $major_re) return 'Failed';
    if ($minor >= $minor_re) return 'Failed';
    return 'Passed';
}
