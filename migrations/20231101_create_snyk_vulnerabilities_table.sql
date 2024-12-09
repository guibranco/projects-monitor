CREATE TABLE snyk_vulnerabilities (
    id SERIAL PRIMARY KEY,
    repository_id INT NOT NULL,
    vulnerability_count INT DEFAULT 0,
    critical_issues INT DEFAULT 0,
    high_issues INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (repository_id) REFERENCES repositories(id)
);

CREATE INDEX idx_repository_id ON snyk_vulnerabilities(repository_id);

-- Add more indices as needed for performance
