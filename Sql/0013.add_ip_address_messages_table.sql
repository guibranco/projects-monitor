ALTER TABLE messages ADD ip_address VARCHAR(45) NULL;
CREATE INDEX idx_messages_ip_address ON messages (ip_address);
