-- What is left to translate with AI (PLAN.md D-248): one row per part of a page, its own
-- words ('page') or one block ('block:{key}'), waiting, done or failed, with the sentence that
-- says why. Kept here rather than in a request or a browser, so a translation that stops
-- carries on from where it was.
CREATE TABLE ai_queue (
    id {{pk}},
    page_id INTEGER NOT NULL,
    part VARCHAR(60) NOT NULL,
    state VARCHAR(12) NOT NULL DEFAULT 'pending',
    message VARCHAR(500) NOT NULL DEFAULT '',
    created_at VARCHAR(19) NOT NULL,
    updated_at VARCHAR(19) NOT NULL,
    CONSTRAINT ai_queue_page FOREIGN KEY (page_id) REFERENCES pages (id) ON DELETE CASCADE
);

CREATE INDEX ai_queue_state ON ai_queue (state, id);
