ALTER TABLE users
MODIFY COLUMN role ENUM(
  'admin',
  'manager',
  'employee',
  'customer',
  'operations_manager',
  'production_staff',
  'inventory_manager',
  'quality_control_inspector'
) DEFAULT 'customer';

INSERT INTO positions (position_name, department_id)
SELECT 'Inventory Manager', 8
WHERE NOT EXISTS (
  SELECT 1 FROM positions WHERE position_name = 'Inventory Manager'
);

INSERT INTO positions (position_name, department_id)
SELECT 'Tailor / Production Staff', 6
WHERE NOT EXISTS (
  SELECT 1 FROM positions WHERE position_name = 'Tailor / Production Staff'
);
