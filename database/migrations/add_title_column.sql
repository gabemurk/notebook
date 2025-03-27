-- Add title column to notes table if it doesn't exist
-- PostgreSQL
CREATE TABLE IF NOT EXISTS notes (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL,
    title VARCHAR(255) DEFAULT 'Untitled Note',
    content TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    sync_status INTEGER DEFAULT 0,
    sync_timestamp TIMESTAMP,
    local_modified BOOLEAN DEFAULT FALSE
);

-- We need to check if the column exists before adding it
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT FROM information_schema.columns 
        WHERE table_name = 'notes' AND column_name = 'title'
    ) THEN
        ALTER TABLE notes ADD COLUMN title VARCHAR(255) DEFAULT 'Untitled Note';
    END IF;
END $$;

-- Make sure we have category column too
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT FROM information_schema.columns 
        WHERE table_name = 'notes' AND column_name = 'category'
    ) THEN
        ALTER TABLE notes ADD COLUMN category VARCHAR(100) DEFAULT '';
    END IF;
END $$;

-- Add tags column if needed
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT FROM information_schema.columns 
        WHERE table_name = 'notes' AND column_name = 'tags'
    ) THEN
        ALTER TABLE notes ADD COLUMN tags TEXT DEFAULT '';
    END IF;
END $$;
