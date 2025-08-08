-- Add check_token for tokenized status checks
ALTER TABLE job_tickets ADD COLUMN check_token VARCHAR(64) NOT NULL AFTER ticket_number;
