ALTER TABLE users 
ADD COLUMN reset_token VARCHAR(255) NULL AFTER password,
ADD COLUMN reset_token_expiration DATETIME NULL AFTER reset_token;
