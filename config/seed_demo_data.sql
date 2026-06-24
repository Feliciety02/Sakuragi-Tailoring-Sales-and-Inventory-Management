-- ============================================================
-- Sakuragi Tailoring - Comprehensive Demo Seed Data
-- Inserts orders, workflow, details, QC, payments across all stages
-- ============================================================

-- ── Products (for order_details) ──
INSERT IGNORE INTO products (product_id, product_name, description, price, category) VALUES
(1, 'Basic T-Shirt', 'Plain cotton t-shirt for custom printing', 250.00, 'Screen Printing'),
(2, 'Premium Polo Shirt', 'High-quality pique polo shirt', 450.00, 'Embroidery'),
(3, 'Baseball Jersey', 'Mesh baseball style jersey', 350.00, 'Sublimation'),
(4, 'Corduroy Jacket', 'Classic corduroy jacket for patches', 1200.00, 'Patches'),
(5, 'Denim Jeans', 'Standard denim jeans for alterations', 800.00, 'Alterations');

-- ── Helper: clear old demo orders (order_id >= 100) ──
DELETE FROM sample_photos WHERE approval_id IN (SELECT approval_id FROM sample_approvals WHERE order_id >= 100);
DELETE FROM sample_approvals WHERE order_id >= 100;
DELETE FROM garment_log WHERE order_id >= 100;
DELETE FROM garment_tracking WHERE order_id >= 100;
DELETE FROM qc_lot_items WHERE lot_inspection_id IN (SELECT lot_inspection_id FROM qc_lot_inspections WHERE order_id >= 100);
DELETE FROM qc_lot_inspections WHERE order_id >= 100;
DELETE FROM aql_config WHERE order_id >= 100;
DELETE FROM material_consumption_log WHERE order_id >= 100;
DELETE FROM order_materials WHERE order_id >= 100;
DELETE FROM rework_log WHERE order_id >= 100;
DELETE FROM production_notes WHERE order_id >= 100;
DELETE FROM task_media WHERE order_id >= 100;
DELETE FROM work_submissions WHERE order_id >= 100;
DELETE FROM qc_inspections WHERE order_id >= 100;
DELETE FROM order_files WHERE order_id >= 100;
DELETE FROM order_custom_data WHERE order_id >= 100;
DELETE FROM order_details WHERE order_id >= 100;
DELETE FROM order_workflow WHERE order_id >= 100;
DELETE FROM payments WHERE order_id >= 100;
DELETE FROM invoices WHERE order_id >= 100;
DELETE FROM shipping WHERE order_id >= 100;
DELETE FROM feedback WHERE order_id >= 100;
DELETE FROM notifications WHERE message LIKE '%ORD-%' OR message LIKE 'Order #%';
DELETE FROM orders WHERE order_id >= 100;

-- ── Orders ──
-- customer IDs: 1 (Fe Anne), 8 (david Tan), 10 (anna londar), 28 (Juan Dela Cruz)
-- branch IDs: 2 (Davao), 3 (Kidapawan), 4 (Tagum)
-- service IDs: 1 (Embroidery), 2 (Sublimation), 3 (Screen Printing), 4 (Alterations), 5 (Patches)

INSERT INTO orders (order_id, branch_id, user_id, service_id, employee_id, order_date, status, total_price, payment_status, expected_completion) VALUES

-- Order 100: Pending Verification (brand new order)
(100, 2, 1, 3, NULL, DATE_SUB(NOW(), INTERVAL 1 DAY), 'Pending', 1800.00, 'Pending', DATE_ADD(NOW(), INTERVAL 14 DAY)),

-- Order 101: Customer Action Required
(101, 3, 8, 1, NULL, DATE_SUB(NOW(), INTERVAL 3 DAY), 'Pending', 2400.00, 'Pending', DATE_ADD(NOW(), INTERVAL 12 DAY)),

-- Order 102: Ready for Production
(102, 4, 10, 2, 17, DATE_SUB(NOW(), INTERVAL 5 DAY), 'In Progress', 1350.00, 'Paid', DATE_ADD(NOW(), INTERVAL 10 DAY)),

-- Order 103: Waiting for Materials
(103, 2, 28, 5, 20, DATE_SUB(NOW(), INTERVAL 7 DAY), 'In Progress', 2000.00, 'Paid', DATE_ADD(NOW(), INTERVAL 8 DAY)),

-- Order 104: Materials Reserved
(104, 3, 1, 3, 21, DATE_SUB(NOW(), INTERVAL 9 DAY), 'In Progress', 3200.00, 'Paid', DATE_ADD(NOW(), INTERVAL 6 DAY)),

-- Order 105: Cutting
(105, 4, 8, 1, 16, DATE_SUB(NOW(), INTERVAL 10 DAY), 'In Progress', 1600.00, 'Paid', DATE_ADD(NOW(), INTERVAL 5 DAY)),

-- Order 106: Sewing
(106, 2, 10, 4, 19, DATE_SUB(NOW(), INTERVAL 12 DAY), 'In Progress', 900.00, 'Paid', DATE_ADD(NOW(), INTERVAL 3 DAY)),

-- Order 107: Embroidery
(107, 3, 28, 1, 17, DATE_SUB(NOW(), INTERVAL 14 DAY), 'In Progress', 3600.00, 'Paid', DATE_ADD(NOW(), INTERVAL 2 DAY)),

-- Order 108: Finishing
(108, 4, 1, 2, 21, DATE_SUB(NOW(), INTERVAL 16 DAY), 'In Progress', 2800.00, 'Paid', DATE_ADD(NOW(), INTERVAL 1 DAY)),

-- Order 109: QC (Quality Inspection)
(109, 2, 8, 5, 22, DATE_SUB(NOW(), INTERVAL 18 DAY), 'In Progress', 1500.00, 'Paid', DATE_ADD(NOW(), INTERVAL 0 DAY)),

-- Order 110: Rework
(110, 3, 10, 3, 16, DATE_SUB(NOW(), INTERVAL 20 DAY), 'In Progress', 2100.00, 'Paid', DATE_SUB(NOW(), INTERVAL -1 DAY)),

-- Order 111: Ready for Release
(111, 4, 1, 4, 19, DATE_SUB(NOW(), INTERVAL 22 DAY), 'In Progress', 750.00, 'Paid', DATE_SUB(NOW(), INTERVAL -2 DAY)),

-- Order 112: Awaiting Final Payment
(112, 2, 28, 2, 17, DATE_SUB(NOW(), INTERVAL 25 DAY), 'In Progress', 4200.00, 'Pending', DATE_SUB(NOW(), INTERVAL -3 DAY)),

-- Order 113: Released (completed)
(113, 3, 8, 1, 16, DATE_SUB(NOW(), INTERVAL 30 DAY), 'Completed', 1900.00, 'Paid', DATE_SUB(NOW(), INTERVAL -5 DAY)),

-- Order 114: Urgent overdue order in Cutting
(114, 4, 10, 3, 20, DATE_SUB(NOW(), INTERVAL 15 DAY), 'In Progress', 5500.00, 'Paid', DATE_SUB(NOW(), INTERVAL 2 DAY)),

-- Order 115: High priority in Sewing
(115, 2, 1, 5, 22, DATE_SUB(NOW(), INTERVAL 11 DAY), 'In Progress', 1200.00, 'Paid', DATE_ADD(NOW(), INTERVAL 4 DAY));

-- ── Order Workflow (stage tracking) ──
INSERT INTO order_workflow (order_id, stage, product_type, priority, started_at, assigned_employee, expected_completion, sample_status) VALUES
(100, 'Pending Verification', 'T-Shirt', 'medium', DATE_SUB(NOW(), INTERVAL 1 DAY), NULL, DATE_ADD(NOW(), INTERVAL 14 DAY), 'not_required'),
(101, 'Customer Action Required', 'Polo Shirt', 'high', DATE_SUB(NOW(), INTERVAL 3 DAY), NULL, DATE_ADD(NOW(), INTERVAL 12 DAY), 'not_required'),
(102, 'Ready for Production', 'Baseball Jersey', 'medium', DATE_SUB(NOW(), INTERVAL 5 DAY), 30, DATE_ADD(NOW(), INTERVAL 10 DAY), 'not_required'),
(103, 'Waiting for Materials', 'Denim Jacket', 'medium', DATE_SUB(NOW(), INTERVAL 7 DAY), 33, DATE_ADD(NOW(), INTERVAL 8 DAY), 'not_required'),
(104, 'Materials Reserved', 'T-Shirt Bundle', 'high', DATE_SUB(NOW(), INTERVAL 9 DAY), 34, DATE_ADD(NOW(), INTERVAL 6 DAY), 'not_required'),
(105, 'Cutting', 'Polo Shirt', 'urgent', DATE_SUB(NOW(), INTERVAL 10 DAY), 29, DATE_ADD(NOW(), INTERVAL 5 DAY), 'approved'),
(106, 'Sewing', 'Denim Jeans', 'medium', DATE_SUB(NOW(), INTERVAL 12 DAY), 32, DATE_ADD(NOW(), INTERVAL 3 DAY), 'not_required'),
(107, 'Embroidery', 'Premium Polo', 'high', DATE_SUB(NOW(), INTERVAL 14 DAY), 30, DATE_ADD(NOW(), INTERVAL 2 DAY), 'approved'),
(108, 'Finishing', 'Baseball Jersey', 'low', DATE_SUB(NOW(), INTERVAL 16 DAY), 34, DATE_ADD(NOW(), INTERVAL 1 DAY), 'submitted'),
(109, 'QC', 'Denim Jacket', 'medium', DATE_SUB(NOW(), INTERVAL 18 DAY), 35, DATE_ADD(NOW(), INTERVAL 0 DAY), 'not_required'),
(110, 'Rework', 'T-Shirt', 'high', DATE_SUB(NOW(), INTERVAL 20 DAY), 29, DATE_SUB(NOW(), INTERVAL -1 DAY), 'approved'),
(111, 'Ready for Release', 'Denim Jeans', 'medium', DATE_SUB(NOW(), INTERVAL 22 DAY), 32, DATE_SUB(NOW(), INTERVAL -2 DAY), 'not_required'),
(112, 'Awaiting Final Payment', 'Baseball Jersey', 'medium', DATE_SUB(NOW(), INTERVAL 25 DAY), 30, DATE_SUB(NOW(), INTERVAL -3 DAY), 'approved'),
(113, 'Released', 'Polo Shirt', 'low', DATE_SUB(NOW(), INTERVAL 30 DAY), 29, DATE_SUB(NOW(), INTERVAL -5 DAY), 'not_required'),
(114, 'Cutting', 'T-Shirt Bulk', 'urgent', DATE_SUB(NOW(), INTERVAL 15 DAY), 33, DATE_SUB(NOW(), INTERVAL 2 DAY), 'not_required'),
(115, 'Sewing', 'Denim Vest', 'high', DATE_SUB(NOW(), INTERVAL 11 DAY), 35, DATE_ADD(NOW(), INTERVAL 4 DAY), 'not_required');

-- Update completed_at for released order
UPDATE order_workflow SET completed_at = DATE_SUB(NOW(), INTERVAL 28 DAY) WHERE order_id = 113;

-- ── Order Details (line items) ──
INSERT INTO order_details (order_detail_id, order_id, product_id, service_id, quantity, unit_price, subtotal, size) VALUES
-- Order 100: 6 T-shirts screen printing
(200, 100, 1, 3, 6, 300.00, 1800.00, 'L'),
-- Order 101: 3 Polo shirts embroidery
(201, 101, 2, 1, 3, 800.00, 2400.00, 'XL'),
-- Order 102: 3 Baseball jerseys sublimation
(202, 102, 3, 2, 3, 450.00, 1350.00, 'M'),
-- Order 103: 5 Denim jackets patches
(203, 103, 4, 5, 5, 400.00, 2000.00, 'L'),
-- Order 104: 4 T-shirts screen printing + extras
(204, 104, 1, 3, 8, 400.00, 3200.00, 'S'),
-- Order 105: 2 Polo shirts embroidery
(205, 105, 2, 1, 2, 800.00, 1600.00, 'M'),
-- Order 106: 3 Denim jeans alterations
(206, 106, 5, 4, 3, 300.00, 900.00, '32'),
-- Order 107: 3 Premium polos embroidery
(207, 107, 2, 1, 3, 1200.00, 3600.00, 'L'),
-- Order 108: 4 Baseball jerseys sublimation
(208, 108, 3, 2, 4, 700.00, 2800.00, 'XL'),
-- Order 109: 5 Denim jackets patches
(209, 109, 4, 5, 5, 300.00, 1500.00, 'M'),
-- Order 110: 3 T-shirts screen printing (rework)
(210, 110, 1, 3, 3, 700.00, 2100.00, 'L'),
-- Order 111: 3 Denim jeans alterations
(211, 111, 5, 4, 3, 250.00, 750.00, '30'),
-- Order 112: 6 Baseball jerseys sublimation
(212, 112, 3, 2, 6, 700.00, 4200.00, 'L'),
-- Order 113: 2 Polo shirts embroidery (completed)
(213, 113, 2, 1, 2, 950.00, 1900.00, 'M'),
-- Order 114: 10 T-shirts screen printing (urgent)
(214, 114, 1, 3, 10, 550.00, 5500.00, 'XL'),
-- Order 115: 4 Denim vests patches
(215, 115, 4, 5, 4, 300.00, 1200.00, 'M');

-- ── Garment Tracking (per-item stage tracking) ──
INSERT INTO garment_tracking (order_detail_id, order_id, stage, employee_id, notes, created_at, updated_at) VALUES
(200, 100, 'Pending Verification', NULL, 'Order received', NOW(), NOW()),
(201, 101, 'Customer Action Required', NULL, 'Awaiting design approval', NOW(), NOW()),
(202, 102, 'Ready for Production', 30, 'Queued for production', NOW(), NOW()),
(203, 103, 'Waiting for Materials', 33, 'Fabric on order', NOW(), NOW()),
(204, 104, 'Materials Reserved', 34, 'Materials allocated', NOW(), NOW()),
(205, 105, 'Cutting', 29, 'Pattern cut started', NOW(), NOW()),
(206, 106, 'Sewing', 32, 'In sewing station 3', NOW(), NOW()),
(207, 107, 'Embroidery', 30, 'Embroidery in progress', NOW(), NOW()),
(208, 108, 'Finishing', 34, 'Final touches', NOW(), NOW()),
(209, 109, 'QC', 35, 'Awaiting QC inspection', NOW(), NOW());

-- ── QC Inspections ──
INSERT INTO qc_inspections (order_id, inspector_id, result, design_accuracy, print_alignment, embroidery_quality, stitching_quality, size_accuracy, fabric_condition, cleanliness, packaging_readiness, feedback, inspected_at, created_at) VALUES
(105, 37, 'Passed', 1, 1, 1, 1, 1, 1, 1, 1, 'All quality metrics passed. Ready for next stage.', DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY)),
(107, 37, 'Passed', 1, 1, 1, 1, 1, 1, 1, 1, 'Embroidery quality excellent. Proceeding.', DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY)),
(110, 37, 'Failed', 1, 0, 1, 1, 0, 1, 0, 1, 'Print alignment off and size accuracy needs adjustment. Sent to rework.', DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY)),
(113, 37, 'Passed', 1, 1, 1, 1, 1, 1, 1, 1, 'Completed order. All checks passed.', DATE_SUB(NOW(), INTERVAL 6 DAY), DATE_SUB(NOW(), INTERVAL 6 DAY));

-- ── Rework Log ──
INSERT INTO rework_log (order_id, triggered_by, from_stage, to_stage, reason, notes, created_at) VALUES
(110, 37, 'QC', 'Rework', 'Print misalignment on left sleeve, size discrepancy of 1cm on chest', 'Adjust print placement and re-measure chest dimensions.', DATE_SUB(NOW(), INTERVAL 1 DAY));

-- ── Production Notes ──
INSERT INTO production_notes (order_id, author_id, content, note_type, created_at) VALUES
(100, 27, 'Order verified and queued for production planning.', 'general', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(102, 27, 'Design approved. Ready for production.', 'handoff', DATE_SUB(NOW(), INTERVAL 5 DAY)),
(105, 30, 'Fabric cut completed. Moving to sewing.', 'general', DATE_SUB(NOW(), INTERVAL 3 DAY)),
(107, 30, 'Embroidery design loaded. Machine setup complete.', 'instruction', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(109, 37, 'QC inspection scheduled for today.', 'general', NOW()),
(110, 37, 'Rework: print alignment and sizing fix needed.', 'issue', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(113, 27, 'Order completed and released to customer.', 'handoff', DATE_SUB(NOW(), INTERVAL 28 DAY)),
(114, 33, 'URGENT: Customer needs this by yesterday. Expediting cutting process.', 'issue', DATE_SUB(NOW(), INTERVAL 2 DAY));

-- ── Payments ──
INSERT INTO payments (order_id, payment_date, amount, currency, payment_method, status, reference_number) VALUES
(102, DATE_SUB(NOW(), INTERVAL 5 DAY), 1350.00, 'PHP', 'GCash', 'Completed', 'GCH-2024-001'),
(103, DATE_SUB(NOW(), INTERVAL 7 DAY), 2000.00, 'PHP', 'Cash', 'Completed', 'CSH-2024-001'),
(104, DATE_SUB(NOW(), INTERVAL 9 DAY), 3200.00, 'PHP', 'Card', 'Completed', 'CRD-2024-001'),
(105, DATE_SUB(NOW(), INTERVAL 10 DAY), 1600.00, 'PHP', 'Online Transfer', 'Completed', 'OT-2024-001'),
(106, DATE_SUB(NOW(), INTERVAL 12 DAY), 900.00, 'PHP', 'GCash', 'Completed', 'GCH-2024-002'),
(107, DATE_SUB(NOW(), INTERVAL 14 DAY), 3600.00, 'PHP', 'Cash', 'Completed', 'CSH-2024-002'),
(108, DATE_SUB(NOW(), INTERVAL 16 DAY), 2800.00, 'PHP', 'Card', 'Completed', 'CRD-2024-002'),
(109, DATE_SUB(NOW(), INTERVAL 18 DAY), 1500.00, 'PHP', 'Online Transfer', 'Completed', 'OT-2024-002'),
(110, DATE_SUB(NOW(), INTERVAL 20 DAY), 2100.00, 'PHP', 'GCash', 'Completed', 'GCH-2024-003'),
(111, DATE_SUB(NOW(), INTERVAL 22 DAY), 750.00, 'PHP', 'Cash', 'Completed', 'CSH-2024-003'),
(112, DATE_SUB(NOW(), INTERVAL 25 DAY), 4200.00, 'PHP', 'Online Transfer', 'Pending', 'OT-2024-003'),
(113, DATE_SUB(NOW(), INTERVAL 30 DAY), 1900.00, 'PHP', 'Card', 'Completed', 'CRD-2024-003'),
(114, DATE_SUB(NOW(), INTERVAL 15 DAY), 5500.00, 'PHP', 'Bank Transfer', 'Completed', 'BNK-2024-001'),
(115, DATE_SUB(NOW(), INTERVAL 11 DAY), 1200.00, 'PHP', 'GCash', 'Completed', 'GCH-2024-004');

-- ── Invoices ──
INSERT INTO invoices (order_id, customer_id, total_amount, payment_status, due_date) VALUES
(102, 10, 1350.00, 'Paid', DATE_ADD(NOW(), INTERVAL 10 DAY)),
(103, 28, 2000.00, 'Paid', DATE_ADD(NOW(), INTERVAL 8 DAY)),
(104, 1, 3200.00, 'Paid', DATE_ADD(NOW(), INTERVAL 6 DAY)),
(105, 8, 1600.00, 'Paid', DATE_ADD(NOW(), INTERVAL 5 DAY)),
(106, 10, 900.00, 'Paid', DATE_ADD(NOW(), INTERVAL 3 DAY)),
(107, 28, 3600.00, 'Paid', DATE_ADD(NOW(), INTERVAL 2 DAY)),
(108, 1, 2800.00, 'Paid', DATE_ADD(NOW(), INTERVAL 1 DAY)),
(109, 8, 1500.00, 'Paid', NOW()),
(110, 10, 2100.00, 'Paid', DATE_SUB(NOW(), INTERVAL -1 DAY)),
(111, 1, 750.00, 'Paid', DATE_SUB(NOW(), INTERVAL -2 DAY)),
(112, 28, 4200.00, 'Pending', DATE_SUB(NOW(), INTERVAL -3 DAY)),
(113, 8, 1900.00, 'Paid', DATE_SUB(NOW(), INTERVAL -5 DAY)),
(114, 10, 5500.00, 'Paid', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(115, 1, 1200.00, 'Paid', DATE_ADD(NOW(), INTERVAL 4 DAY));

-- ── Sample Approvals ──
INSERT INTO sample_approvals (order_id, submitted_by, reviewed_by, status, notes, submitted_at, reviewed_at) VALUES
(105, 30, 1, 'approved', 'Sample looks great. Proceed with bulk production.', DATE_SUB(NOW(), INTERVAL 6 DAY), DATE_SUB(NOW(), INTERVAL 5 DAY)),
(107, 30, 28, 'approved', 'Embroidery sample approved by customer.', DATE_SUB(NOW(), INTERVAL 5 DAY), DATE_SUB(NOW(), INTERVAL 4 DAY)),
(108, 34, NULL, 'pending', 'Awaiting customer photo approval.', DATE_SUB(NOW(), INTERVAL 1 DAY), NULL),
(110, 29, 8, 'approved', 'Sample approved despite noted issues.', DATE_SUB(NOW(), INTERVAL 3 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY)),
(112, 30, 1, 'approved', 'Customer approved the sample design.', DATE_SUB(NOW(), INTERVAL 10 DAY), DATE_SUB(NOW(), INTERVAL 9 DAY));

-- ── Notifications ──
INSERT INTO notifications (user_id, message, status, created_at) VALUES
(27, 'Order #ORD-114 is overdue. Customer marked as urgent.', 'Sent', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(27, 'QC inspection completed for Order #ORD-105.', 'Sent', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(27, 'Order #ORD-110 requires rework attention.', 'Sent', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(30, 'New order #ORD-107 assigned to you for embroidery.', 'Sent', DATE_SUB(NOW(), INTERVAL 14 DAY)),
(29, 'Order #ORD-105 cutting stage assigned.', 'Sent', DATE_SUB(NOW(), INTERVAL 10 DAY)),
(32, 'Alteration order #ORD-106 ready for sewing.', 'Sent', DATE_SUB(NOW(), INTERVAL 12 DAY));

-- ── Audit Log ──
INSERT INTO audit_logs (user_id, action, details, created_at) VALUES
(27, 'Bulk data seed', 'Inserted demo orders 100-115 for testing', NOW());
