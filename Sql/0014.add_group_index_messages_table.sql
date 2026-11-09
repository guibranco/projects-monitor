/* tsqllint-disable */
CREATE INDEX IF NOT EXISTS idx_messages_application_id ON messages (application_id);
CREATE INDEX IF NOT EXISTS idx_messages_group ON messages (application_id, line, file(100), class(50), function(50), user_agent(100), message(64));
