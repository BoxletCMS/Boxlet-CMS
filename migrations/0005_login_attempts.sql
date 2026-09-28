-- Login rate limiting only; the audit log gets its own table (Slice 8). IPs and emails
-- are HMAC hashes keyed by APP_KEY, never raw values. Rows older than the rate-limit
-- window are pruned on write.
CREATE TABLE login_attempts (
    ip_hash VARCHAR(64) NOT NULL,
    email_hash VARCHAR(64) NOT NULL,
    successful SMALLINT NOT NULL,
    attempted_at VARCHAR(19) NOT NULL
);
CREATE INDEX login_attempts_ip ON login_attempts (ip_hash, attempted_at);
CREATE INDEX login_attempts_email ON login_attempts (email_hash, attempted_at);
