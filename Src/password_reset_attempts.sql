CREATE TABLE password_reset_attempts (
    ip_address VARCHAR(45) NOT NULL,
    created_at DATETIME NOT NULL
);
