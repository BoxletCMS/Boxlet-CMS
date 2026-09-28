-- Visitors per page and day (PLAN.md D-051). stats_views.visitors counts a visitor once a
-- day, in the row of their first view, so it cannot say how many visitors a SECOND page
-- had; this can. One row per day and path.
CREATE TABLE stats_page_visitors (
    day VARCHAR(10) NOT NULL,
    path VARCHAR(255) NOT NULL,
    visitors INTEGER NOT NULL DEFAULT 0,
    CONSTRAINT stats_page_visitors_unique UNIQUE (day, path)
);
