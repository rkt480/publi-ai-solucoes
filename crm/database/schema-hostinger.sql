CREATE TABLE IF NOT EXISTS leads (
  id VARCHAR(32) PRIMARY KEY,
  name VARCHAR(160) NOT NULL,
  whatsapp VARCHAR(40) NOT NULL,
  company VARCHAR(180) NOT NULL,
  segment VARCHAR(120) NULL,
  advertises VARCHAR(20) NOT NULL,
  message TEXT NULL,
  page TEXT NULL,
  utm_source VARCHAR(120) NULL,
  utm_medium VARCHAR(120) NULL,
  utm_campaign VARCHAR(180) NULL,
  utm_content VARCHAR(180) NULL,
  utm_term VARCHAR(180) NULL,
  referrer TEXT NULL,
  landing_path TEXT NULL,
  status ENUM('novo', 'contatado', 'followup', 'proposta', 'fechado', 'perdido') NOT NULL DEFAULT 'novo',
  notes TEXT NULL,
  whatsapp_status VARCHAR(30) NOT NULL DEFAULT 'pendente',
  whatsapp_sent_at DATETIME NULL,
  whatsapp_error TEXT NULL,
  followup_flow_id INT NULL,
  followup_started_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS followup_flows (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(160) NOT NULL,
  description TEXT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS followup_steps (
  id INT AUTO_INCREMENT PRIMARY KEY,
  flow_id INT NOT NULL,
  step_order INT NOT NULL,
  delay_minutes INT NOT NULL DEFAULT 0,
  message TEXT NOT NULL,
  created_at DATETIME NOT NULL,
  FOREIGN KEY (flow_id) REFERENCES followup_flows(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS followup_queue (
  id INT AUTO_INCREMENT PRIMARY KEY,
  lead_id VARCHAR(32) NOT NULL,
  flow_id INT NOT NULL,
  step_id INT NOT NULL,
  step_order INT NOT NULL,
  scheduled_at DATETIME NOT NULL,
  sent_at DATETIME NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'pendente',
  error TEXT NULL,
  created_at DATETIME NOT NULL,
  INDEX idx_due (status, scheduled_at),
  FOREIGN KEY (flow_id) REFERENCES followup_flows(id) ON DELETE CASCADE,
  FOREIGN KEY (step_id) REFERENCES followup_steps(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS followup_step_history (
  id INT AUTO_INCREMENT PRIMARY KEY,
  lead_id VARCHAR(32) NOT NULL,
  flow_id INT NOT NULL,
  step_order INT NOT NULL,
  status VARCHAR(30) NOT NULL,
  sent_at DATETIME NULL,
  error TEXT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uniq_lead_flow_order (lead_id, flow_id, step_order),
  INDEX idx_history_lookup (lead_id, flow_id, step_order, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
