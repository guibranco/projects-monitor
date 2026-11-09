ALTER TABLE `messages`
    ADD `ip_address` VARCHAR(45) NULL,
    ADD INDEX `idx_messages_ip_address` (`ip_address`);
