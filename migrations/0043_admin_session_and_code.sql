-- WHAT THE SECURITY REVIEW ADDED TO THE ADMIN'S ROW (PLAN.md O-66, O-67, the owner, 2026-10-12).
--
-- The time step of the last two-step code accepted, so the same code, or an older one, is
-- refused while it would still be valid: a code seen over a shoulder worked again for the
-- same minute. NULL until a code is used.
ALTER TABLE admin ADD COLUMN totp_last_step BIGINT NULL;

-- The generation of this admin's logins. A session carries the one it began under, and a
-- password changed or reset moves it on, so every other session ends with it.
ALTER TABLE admin ADD COLUMN session_gen INTEGER NOT NULL DEFAULT 0;
