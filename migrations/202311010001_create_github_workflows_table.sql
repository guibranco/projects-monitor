CREATE TABLE github_workflows (
  id INT AUTO_INCREMENT PRIMARY KEY,
  repository_id INT NOT NULL,
  workflow_name VARCHAR(255) NOT NULL,
  last_run_status VARCHAR(50),
  last_run_timestamp DATETIME,
  FOREIGN KEY (repository_id) REFERENCES repositories(id)
);
