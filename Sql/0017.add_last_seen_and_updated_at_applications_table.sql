/* tsqllint-disable */
-- updated_at: when the application was last changed (renamed, key or token
-- rotated). Set by the code, NOT with ON UPDATE CURRENT_TIMESTAMP, so that
-- refreshing last_seen_at does not count as an update.
-- last_seen_at: when the application last sent a message (refreshed on every
-- message stored for it).
ALTER TABLE applications ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE applications ADD COLUMN IF NOT EXISTS last_seen_at TIMESTAMP NULL DEFAULT NULL;

-- Backfill last_seen_at from the messages already stored.
UPDATE applications
SET last_seen_at = (
    SELECT MAX(messages.created_at)
    FROM messages
    WHERE messages.application_id = applications.id
);
