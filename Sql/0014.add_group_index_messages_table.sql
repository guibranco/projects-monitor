CREATE INDEX idx_messages_group ON messages (application_id, line, file(191), class(100), function(100), user_agent(191), message(100));
