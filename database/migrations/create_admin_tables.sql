-- Sync History Table
CREATE TABLE IF NOT EXISTS sync_history (
    id SERIAL PRIMARY KEY,
    sync_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    direction VARCHAR(20) NOT NULL,
    status VARCHAR(20) NOT NULL,
    error_message TEXT,
    affected_rows INTEGER
);

-- Database Stats Table
CREATE TABLE IF NOT EXISTS database_stats (
    id SERIAL PRIMARY KEY,
    stat_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    db_type VARCHAR(20) NOT NULL,
    total_users INTEGER NOT NULL,
    total_notes INTEGER NOT NULL,
    total_size_bytes BIGINT NOT NULL
);

-- Backup History Table
CREATE TABLE IF NOT EXISTS backup_history (
    id SERIAL PRIMARY KEY,
    backup_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    db_type VARCHAR(20) NOT NULL,
    file_path TEXT NOT NULL,
    file_size_bytes BIGINT NOT NULL,
    status VARCHAR(20) NOT NULL,
    error_message TEXT
);

-- Auto Sync Settings Table
CREATE TABLE IF NOT EXISTS auto_sync_settings (
    id SERIAL PRIMARY KEY,
    enabled BOOLEAN NOT NULL DEFAULT false,
    interval_minutes INTEGER NOT NULL DEFAULT 60,
    last_run TIMESTAMP,
    next_run TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
