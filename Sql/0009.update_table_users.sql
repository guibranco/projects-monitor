ALTER TABLE users 
ADD COLUMN reset_token VARCHAR(255) NULL AFTER email,
ADD COLUMN reset_token_expiration DATETIME NULL AFTER reset_token;
