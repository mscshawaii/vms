CREATE TABLE IF NOT EXISTS equipment_audit_log (
  audit_id INT AUTO_INCREMENT PRIMARY KEY,
  equipment_id INT NOT NULL,
  vessel_id INT NULL,
  actor_user_id INT NULL,
  action VARCHAR(50) NOT NULL,
  equipment_snapshot JSON NULL,
  reason VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_equipment_audit_equipment_id (equipment_id),
  INDEX idx_equipment_audit_vessel_id (vessel_id),
  INDEX idx_equipment_audit_created_at (created_at)
);
