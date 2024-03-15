DROP TABLE IF EXISTS `applications`;

CREATE TABLE
    `applications` (
        `id` int (11) NOT NULL AUTO_INCREMENT,
        `name` varchar(255) NOT NULL,
        `key` varchar(100) NOT NULL,
        `token` char(32) NOT NULL,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE (`key`)
    ) ENGINE = InnoDB DEFAULT CHARSET = utf8;

DROP TABLE IF EXISTS `messages`;

CREATE TABLE
    `messages` (
        `id` int NOT NULL AUTO_INCREMENT,
        `application_id` int NOT NULL,
        `class` varchar(255) NOT NULL,
        `function` varchar(255) NOT NULL,
        `file` varchar(255) NOT NULL,
        `line` int (11) NOT NULL,
        `object` text NULL,
        `type` varchar(255) NULL,
        `args` text NULL,
        `message` text NOT NULL,
        `details` text NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`)
    ) ENGINE = InnoDB DEFAULT CHARSET = utf8;
