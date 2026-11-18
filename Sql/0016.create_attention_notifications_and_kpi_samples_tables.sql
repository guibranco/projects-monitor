/* tsqllint-disable */
-- Critical attention items already pushed through OneSignal, by fingerprint.
-- An item is pushed again when its since value changes, or after it is no
-- longer raised (its row is then deleted).
DROP TABLE IF EXISTS attention_notifications;

CREATE TABLE attention_notifications (
    fingerprint VARCHAR(191) NOT NULL PRIMARY KEY,
    since VARCHAR(25) NOT NULL DEFAULT '',
    notified_at DATETIME NOT NULL
);

-- Server-side history of the dashboard's KPI tiles, at most one sample per
-- metric per minute (sampled_at is truncated to the minute, UTC). Rows older
-- than kpi.history.retentionHours are deleted as new samples arrive.
DROP TABLE IF EXISTS kpi_samples;

CREATE TABLE kpi_samples (
    metric VARCHAR(100) NOT NULL,
    sampled_at DATETIME NOT NULL,
    value DOUBLE NOT NULL,
    PRIMARY KEY (metric, sampled_at),
    INDEX idx_kpi_samples_sampled_at (sampled_at)
);
