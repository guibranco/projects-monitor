/* tsqllint-disable */
ALTER TABLE messages ADD COLUMN IF NOT EXISTS ip_address VARCHAR(45) NULL;
CREATE INDEX IF NOT EXISTS idx_messages_ip_address ON messages (ip_address);
