-- Development qualification schema for the transaction outbox seam.
CREATE TABLE outbox_events (
    event_id VARCHAR(64) NOT NULL,
    event_type VARCHAR(128) NOT NULL,
    event_version INT UNSIGNED NOT NULL,
    aggregate_type VARCHAR(128) NOT NULL,
    aggregate_id VARCHAR(128) NOT NULL,
    aggregate_version BIGINT UNSIGNED NOT NULL,
    payload JSON NOT NULL,
    published_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (event_id),
    KEY outbox_pending_idx (published_at, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE outbox_consumer_receipts (
    consumer_id VARCHAR(128) NOT NULL,
    event_id VARCHAR(64) NOT NULL,
    received_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (consumer_id, event_id),
    CONSTRAINT outbox_receipt_event_fk FOREIGN KEY (event_id)
        REFERENCES outbox_events (event_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE outbox_dead_letters (
    dead_letter_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id VARCHAR(64) NOT NULL,
    reason VARCHAR(200) NOT NULL,
    attempts INT UNSIGNED NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (dead_letter_id),
    KEY outbox_dead_letter_event_idx (event_id),
    CONSTRAINT outbox_dead_letter_event_fk FOREIGN KEY (event_id)
        REFERENCES outbox_events (event_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
