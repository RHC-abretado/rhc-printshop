-- Add protected flag to users table and protect developer account
ALTER TABLE users ADD COLUMN protected TINYINT(1) NOT NULL DEFAULT 0;
UPDATE users SET protected = 1 WHERE username = 'abretado';

