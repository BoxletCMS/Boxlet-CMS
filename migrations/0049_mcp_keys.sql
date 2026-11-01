-- KEYS AN AI CLIENT USES TO READ AND WRITE THE SITE OVER MCP (PLAN.md O-78, the owner, 2026-10-23:
-- "popis je ok"; a key for the first version, "može prva verzija s ključem").
--
-- A key is shown to the owner once, when it is made, and kept only as its keyed hash (HMAC with
-- APP_KEY), so the database never holds one that works. can_write: 0 reads only; 1 also makes
-- drafts, pictures and design sets. revoked_at set, and the key opens nothing again.
--
-- mcp_calls: one row per call, for the per-key limit; rows older than the window are deleted as
-- calls come in, as form_sends' are (D-269).
CREATE TABLE mcp_keys (
    id {{pk}},
    name VARCHAR(190) NOT NULL,
    key_hash VARCHAR(64) NOT NULL,
    can_write INTEGER NOT NULL,
    created_at VARCHAR(19) NOT NULL,
    last_used_at VARCHAR(19) NULL,
    revoked_at VARCHAR(19) NULL
);

CREATE UNIQUE INDEX mcp_keys_hash ON mcp_keys (key_hash);

CREATE TABLE mcp_calls (
    id {{pk}},
    key_id INTEGER NOT NULL,
    created_at VARCHAR(19) NOT NULL
);

CREATE INDEX mcp_calls_key ON mcp_calls (key_id, created_at);
