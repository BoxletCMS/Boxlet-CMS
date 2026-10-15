-- WHEN A FORM WAS SENT, AND FROM WHOM, FOR THE PER-SENDER LIMIT ONLY (PLAN.md D-269, the owner,
-- 2026-10-14). Keeping what a visitor wrote is now the owner's choice per form, off by default
-- (GDPR); the limit of five sends in ten minutes counted the kept messages, and would count
-- nothing for a form that keeps none. So each send leaves a row here: the form, the sender's
-- address as the same keyed hash form_submissions holds (never the address), and the time.
-- Nothing the visitor wrote. Rows older than the limit's window are deleted as sends come in.
CREATE TABLE form_sends (
    id {{pk}},
    form_id INTEGER NOT NULL,
    ip_hash VARCHAR(64) NOT NULL,
    created_at VARCHAR(19) NOT NULL,
    CONSTRAINT form_sends_form_fk FOREIGN KEY (form_id) REFERENCES forms (id) ON DELETE CASCADE
);

CREATE INDEX form_sends_ip ON form_sends (ip_hash, created_at);
