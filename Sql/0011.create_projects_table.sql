CREATE TABLE projects (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    repository_url VARCHAR(255) NOT NULL UNIQUE
        CHECK (repository_url ~ '^https?://[^\s/$.?#].[^\s]*$'),
    github_pages_url VARCHAR(255) DEFAULT NULL
        CHECK (github_pages_url IS NULL OR github_pages_url ~ '^https?://[^\s/$.?#].[^\s]*$'),
    type ENUM('library', 'project') NOT NULL,
    private BOOLEAN NOT NULL DEFAULT FALSE,
    main_language VARCHAR(100) DEFAULT NULL,
    last_commit TIMESTAMP NULL,
    latest_release VARCHAR(50) DEFAULT NULL,
    version_url VARCHAR(255) DEFAULT NULL,
    latest_version VARCHAR(50) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CHECK (type = 'library' OR (type = 'project' AND version_url IS NOT NULL))
);
