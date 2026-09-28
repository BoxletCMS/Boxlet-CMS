-- The single admin account. Email is stored lower-cased. Timestamps are UTC
-- 'Y-m-d H:i:s' strings, which sort and compare the same on both engines.
CREATE TABLE admin (
    id {{pk}},
    email VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    totp_secret VARCHAR(255) NULL,
    recovery_codes_json TEXT NULL,
    created_at VARCHAR(19) NOT NULL,
    CONSTRAINT admin_email_unique UNIQUE (email)
);
