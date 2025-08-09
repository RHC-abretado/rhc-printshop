-- Create requestor_token table for storing persistent email tokens
CREATE TABLE requestor_token (
    email VARCHAR(255) NOT NULL PRIMARY KEY,
    token VARCHAR(64) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);
