-- Migration: Add missing indexes for query performance
-- Applied: 2025-06-18

-- orders table: index on status and payment_status for filtering
ALTER TABLE orders ADD INDEX idx_orders_status (status);
ALTER TABLE orders ADD INDEX idx_orders_payment_status (payment_status);
ALTER TABLE orders ADD INDEX idx_orders_order_date (order_date);
ALTER TABLE orders ADD INDEX idx_orders_expected_completion (expected_completion);

-- order_details: index on order_id and product_id
ALTER TABLE order_details ADD INDEX idx_order_details_product_id (product_id);

-- order_workflow: index on stage for production board queries
ALTER TABLE order_workflow ADD INDEX idx_workflow_stage (stage);

-- inventory: index on item_name for search, supply_type_id for category filtering
ALTER TABLE inventory ADD INDEX idx_inventory_item_name (item_name);
ALTER TABLE inventory ADD INDEX idx_inventory_reorder_level (reorder_level);

-- notifications: index on status for unread count queries
ALTER TABLE notifications ADD INDEX idx_notifications_status (status);
ALTER TABLE notifications ADD INDEX idx_notifications_created_at (created_at);

-- users: index on role for role-based queries
ALTER TABLE users ADD INDEX idx_users_role (role);

-- employees: index on position_id for position lookups
ALTER TABLE employees ADD INDEX idx_employees_position_id (position_id);
ALTER TABLE employees ADD INDEX idx_employees_status_id (status_id);
ALTER TABLE employees ADD INDEX idx_employees_hire_date (hire_date);

-- payments: index on payment_method and status
ALTER TABLE payments ADD INDEX idx_payments_method (payment_method);
ALTER TABLE payments ADD INDEX idx_payments_status (status);

-- work_submissions: index on status for QC dashboard
ALTER TABLE work_submissions ADD INDEX idx_wsub_status (status);
ALTER TABLE work_submissions ADD INDEX idx_wsub_submission_date (submission_date);

-- qc_inspections: index on result for pass/fail analytics
ALTER TABLE qc_inspections ADD INDEX idx_qc_result (result);

-- supplier_supplies: composite index for supplier-supply lookups
ALTER TABLE supplier_supplies ADD INDEX idx_sup_supply (supplier_id, supply_type_id);

-- shipping: index on delivery_status
ALTER TABLE shipping ADD INDEX idx_shipping_status (delivery_status);
