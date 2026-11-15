-- Snoozes and acknowledgements of attention items, by fingerprint.
-- state is "snoozed" (hidden until snoozed_until, UTC) or "acknowledged"
-- (counted as seen while the item's since value equals acknowledged_since).
DROP TABLE IF EXISTS attention_states;

CREATE TABLE attention_states (
    fingerprint VARCHAR(191) NOT NULL PRIMARY KEY,
    state VARCHAR(20) NOT NULL,
    snoozed_until DATETIME NULL,
    acknowledged_since VARCHAR(25) NULL,
    user_id INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Overrides of the defaults in Src/Library/attention-thresholds.json, by name.
DROP TABLE IF EXISTS attention_thresholds;

CREATE TABLE attention_thresholds (
    name VARCHAR(100) NOT NULL PRIMARY KEY,
    value DECIMAL(14, 2) NOT NULL
);
