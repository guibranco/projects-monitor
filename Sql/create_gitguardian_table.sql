CREATE TABLE gitguardian_secrets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    secret_type VARCHAR(255) NOT NULL,
    repository VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    line_number INT NOT NULL,
    detected_at DATETIME NOT NULL,
    metadata JSON,
    INDEX idx_repository (repository),
    INDEX idx_detected_at (detected_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
