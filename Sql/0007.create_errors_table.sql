DROP TABLE IF EXISTS `errors`;

CREATE TABLE
    `errors` (
        `id` int NOT NULL AUTO_INCREMENT,
        `date` DATETIME NOT NULL,
        `error_log_path` varchar(255) NOT NULL,
        `file` varchar(255) NOT NULL,
        `line` int (11) NOT NULL,        
        `message` text NOT NULL,
        `details` text NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`)
    ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE utf8mb4_unicode_ci;
