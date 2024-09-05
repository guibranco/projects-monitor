DROP TABLE IF EXISTS `errors`;

CREATE TABLE
    `errors` (
        `id` int NOT NULL AUTO_INCREMENT,
        `error_log_path` varchar(255) NOT NULL,
        `date` datetime NOT NULL,
        `error` text NOT NULL,
        `error_multiline` text NOT NULL,
        `file` varchar(255) NOT NULL,
        `line` int (11) NOT NULL,
        `stack_trace` text NULL,
        `stack_trace_details` text NULL,
        `repository` varchar(255) NULL,
        `issue_number` int NULL,
        `issue_created_at` timestamp NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (`id`)
    ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE utf8mb4_unicode_ci;
