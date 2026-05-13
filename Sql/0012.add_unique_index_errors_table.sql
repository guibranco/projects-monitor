ALTER TABLE `errors`
    ADD UNIQUE INDEX `idx_errors_unique` (`error_log_path`(191), `date`, `file`(191), `line`);
