DROP TABLE IF EXISTS `attention_states`;

CREATE TABLE `attention_states` (
    `fingerprint` VARCHAR(191) NOT NULL PRIMARY KEY,
    `state` ENUM('snoozed', 'acknowledged') NOT NULL,
    `snoozed_until` DATETIME NULL COMMENT 'UTC; set when state is snoozed',
    `acknowledged_since` VARCHAR(25) NULL COMMENT 'The item''s since value when acknowledged; the acknowledgement lapses when it changes',
    `user_id` INT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (state)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `attention_thresholds`;

CREATE TABLE `attention_thresholds` (
    `name` VARCHAR(100) NOT NULL PRIMARY KEY COMMENT 'A key of Src/Library/attention-thresholds.json',
    `value` DECIMAL(14, 2) NOT NULL,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE utf8mb4_unicode_ci;
