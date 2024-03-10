
DROP TABLE IF EXISTS `applications`;

CREATE TABLE
    `applications` (
        `id` int (11) NOT NULL AUTO_INCREMENT,
        `name` varchar(255) NOT NULL,
        `key` varchar(100) NOT NULL,
        `secret` char(32) NOT NULL,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE = InnoDB DEFAULT CHARSET = utf8;

DROP TABLE IF EXISTS `errors`;

CREATE TABLE
    `errors` (
        `id` int (11) NOT NULL AUTO_INCREMENT,        
        `application_id` INT NOT NULL,
        `class` varchar(255) NOT NULL,
        `method` varchar(255) NOT NULL,
        `file` varchar(255) NOT NULL,
        `line` int NOT NULL,
        `message` text NOT NULL,
        `stack_trace` text NOT NULL,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`)
    ) ENGINE = InnoDB DEFAULT CHARSET = utf8;

