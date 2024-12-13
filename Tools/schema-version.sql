DROP TABLE IF EXISTS `schema_version`;

CREATE TABLE
    `schema_version` (
        `Sequence` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `Filename` VARCHAR(255) NOT NULL,
        `Checksum` CHAR(64) NOT NULL,
        `Date` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`Sequence`),
        UNIQUE (`Filename`),
        UNIQUE (`Checksum`)
    ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE utf8mb4_unicode_ci;
