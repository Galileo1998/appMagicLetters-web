-- Additive only: historical letters remain unmarked and render as V1.
CREATE TABLE IF NOT EXISTS letter_presentation_versions (
 letter_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
 content_version TINYINT UNSIGNED NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
