CREATE TABLE codacy_info (
    id INT AUTO_INCREMENT PRIMARY KEY,
    repository_id INT NOT NULL,
    quality_score DECIMAL(5,2),
    issues_count INT,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (repository_id) REFERENCES repositories(id)
);

CREATE INDEX idx_repository_id ON codacy_info(repository_id);