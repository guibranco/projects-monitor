ALTER TABLE `messages`
    ADD `correlation_id` VARCHAR(36) NULL,
    ADD `user_agent` VARCHAR(255) NULL;