<?php

// Roles
define('ROLE_ADMIN', 'admin');
define('ROLE_MANAGER', 'manager');
define('ROLE_EMPLOYEE', 'employee');
define('ROLE_CUSTOMER', 'customer');

// User Status
define('STATUS_ACTIVE', 'Active');
define('STATUS_INACTIVE', 'Inactive');
define('STATUS_SUSPENDED', 'Suspended');

// Order Status
define('ORDER_PENDING', 'Pending');
define('ORDER_IN_PROGRESS', 'In Progress');
define('ORDER_COMPLETED', 'Completed');
define('ORDER_CANCELLED', 'Cancelled');
define('ORDER_REFUNDED', 'Refunded');

// Payment Status
define('PAYMENT_PENDING', 'Pending');
define('PAYMENT_PAID', 'Paid');
define('PAYMENT_REFUNDED', 'Refunded');

// Production Workflow Stages (MES)
define('STAGE_ORDER_RECEIVED', 'Order Received');
define('STAGE_DESIGN_REVIEW', 'Design Review');
define('STAGE_MATERIAL_PREP', 'Material Preparation');
define('STAGE_CUTTING', 'Cutting');
define('STAGE_PRINTING', 'Printing / Embroidery');
define('STAGE_SEWING', 'Sewing & Assembly');
define('STAGE_SAMPLE_REVIEW', 'Sample Review');
define('STAGE_BULK_PRODUCTION', 'Bulk Production');
define('STAGE_QUALITY_INSPECTION', 'Quality Inspection');
define('STAGE_REWORK', 'Rework');
define('STAGE_PACKAGING', 'Packaging');
define('STAGE_READY_PICKUP', 'Ready for Pickup');
define('STAGE_COMPLETED', 'Completed');

// Customer-facing simplified stages
define('CSTAGE_CONFIRMED', 'Order Confirmed');
define('CSTAGE_PRODUCTION', 'In Production');
define('CSTAGE_QUALITY', 'Quality Check');
define('CSTAGE_PACKAGING_C', 'Packaging');
define('CSTAGE_READY', 'Ready for Pickup');
define('CSTAGE_DONE', 'Completed');

// Stage display labels and colors for the kanban board
$STAGE_CONFIG = [
    'Order Received'       => ['label' => 'Order Received',      'color' => '#f59e0b', 'icon' => 'fas fa-inbox'],
    'Design Review'        => ['label' => 'Design Review',       'color' => '#8b5cf6', 'icon' => 'fas fa-pencil-ruler'],
    'Material Preparation' => ['label' => 'Material Prep',       'color' => '#3b82f6', 'icon' => 'fas fa-roll'],
    'Sample Review'        => ['label' => 'Sample Review',       'color' => '#7c3aed', 'icon' => 'fas fa-flask'],
    'Bulk Production'      => ['label' => 'Bulk Production',     'color' => '#2563eb', 'icon' => 'fas fa-industry'],
    'Cutting'              => ['label' => 'Cutting',             'color' => '#06b6d4', 'icon' => 'fas fa-cut'],
    'Printing / Embroidery'=> ['label' => 'Print / Embroider',   'color' => '#ec4899', 'icon' => 'fas fa-print'],
    'Sewing & Assembly'    => ['label' => 'Sewing & Assembly',   'color' => '#f97316', 'icon' => 'fas fa-tshirt'],
    'Quality Inspection'   => ['label' => 'Quality Inspection',  'color' => '#10b981', 'icon' => 'fas fa-search'],
    'Rework'               => ['label' => 'Rework',              'color' => '#ef4444', 'icon' => 'fas fa-undo-alt'],
    'Packaging'            => ['label' => 'Packaging',           'color' => '#6366f1', 'icon' => 'fas fa-box'],
    'Ready for Pickup'     => ['label' => 'Ready for Pickup',    'color' => '#14b8a6', 'icon' => 'fas fa-check-circle'],
    'Completed'            => ['label' => 'Completed',           'color' => '#10b981', 'icon' => 'fas fa-check-double'],
];

// Customer stage mapping (internal stage → customer-facing stage)
$CUSTOMER_STAGE_MAP = [
    'Order Received'       => 'Order Confirmed',
    'Design Review'        => 'In Production',
    'Material Preparation' => 'In Production',
    'Cutting'              => 'In Production',
    'Printing / Embroidery'=> 'In Production',
    'Sewing & Assembly'    => 'In Production',
    'Quality Inspection'   => 'Quality Check',
    'Rework'               => 'In Production',
    'Packaging'            => 'Packaging',
    'Ready for Pickup'     => 'Ready for Pickup',
    'Completed'            => 'Completed',
];

// Position → stage mapping (which stages each position can work on)
$POSITION_STAGES = [
    1 => [STAGE_CUTTING, STAGE_SEWING],                               // Tailor
    2 => [STAGE_SEWING],                                               // Senior Tailor
    3 => [STAGE_SEWING],                                               // Alteration Specialist
    4 => [STAGE_DESIGN_REVIEW, STAGE_CUTTING],                        // Pattern Maker
    5 => [STAGE_PRINTING],                                             // Sublimation Technician
    6 => [STAGE_PRINTING],                                             // Screen Printing Operator
    7 => [STAGE_PRINTING],                                             // Print Finisher
    8 => [STAGE_PRINTING],                                             // Embroidery Machine Operator
    9 => [STAGE_PRINTING],                                             // Embroidery Technician
    10 => [STAGE_QUALITY_INSPECTION, STAGE_REWORK],                   // Quality Control Inspector
    11 => [STAGE_PACKAGING, STAGE_READY_PICKUP],                      // Packing Staff
    12 => [STAGE_MATERIAL_PREP, STAGE_CUTTING, STAGE_SEWING],         // Production Staff
    13 => [STAGE_ORDER_RECEIVED, STAGE_DESIGN_REVIEW, STAGE_MATERIAL_PREP, STAGE_CUTTING, STAGE_PRINTING, STAGE_SEWING, STAGE_QUALITY_INSPECTION, STAGE_REWORK, STAGE_PACKAGING, STAGE_READY_PICKUP], // Floor Supervisor
    14 => [STAGE_ORDER_RECEIVED, STAGE_DESIGN_REVIEW, STAGE_PACKAGING, STAGE_READY_PICKUP], // Shop Assistant
];

function getPositionStages($position_id) {
    global $POSITION_STAGES;
    return $POSITION_STAGES[(int)$position_id] ?? [STAGE_ORDER_RECEIVED, STAGE_DESIGN_REVIEW, STAGE_MATERIAL_PREP, STAGE_CUTTING, STAGE_PRINTING, STAGE_SEWING, STAGE_QUALITY_INSPECTION, STAGE_REWORK, STAGE_PACKAGING, STAGE_READY_PICKUP];
}

function getEmployeePosition($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT e.position_id, p.position_name FROM employees e JOIN positions p ON e.position_id = p.position_id WHERE e.user_id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetch();
}

function getStageProgress($stage)
{
    $map = [
        STAGE_ORDER_RECEIVED => 5,
        STAGE_DESIGN_REVIEW => 15,
        STAGE_MATERIAL_PREP => 25,
        STAGE_SAMPLE_REVIEW => 35,
        STAGE_BULK_PRODUCTION => 40,
        STAGE_CUTTING => 45,
        STAGE_PRINTING => 55,
        STAGE_SEWING => 65,
        STAGE_QUALITY_INSPECTION => 80,
        STAGE_REWORK => 50,
        STAGE_PACKAGING => 90,
        STAGE_READY_PICKUP => 95,
        STAGE_COMPLETED => 100,
    ];
    return $map[$stage] ?? 0;
}
