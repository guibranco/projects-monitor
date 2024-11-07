DROP TABLE IF EXISTS `errors`;

CREATE TABLE
    `errors` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
;
        `error_message` TEXT NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
