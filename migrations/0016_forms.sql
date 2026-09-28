-- Forms and what visitors send through them (Slice 7, PLAN.md D-046).
--
-- A form belongs to one locale, like a menu: its labels, its button and its replies are
-- words, and a translation has its own rather than borrowing the source language's.
--
-- fields_json is the list of fields, each {key, type, label, required, options};
-- settings_json the button, the thank-you message, and whether and how the site mails the
-- owner and replies to the sender. Both are shaped and checked by App\Modules\Forms\Form,
-- the only place that writes them.
CREATE TABLE forms (
    id {{pk}},
    locale VARCHAR(12) NOT NULL,
    name VARCHAR(190) NOT NULL,
    fields_json TEXT NOT NULL,
    settings_json TEXT NOT NULL,
    created_at VARCHAR(19) NOT NULL,
    updated_at VARCHAR(19) NOT NULL,
    CONSTRAINT forms_locale_fk FOREIGN KEY (locale) REFERENCES locales (code)
);

-- One message sent through a form. data_json keeps each answer WITH the label it was asked
-- under, so renaming or removing a field later leaves old messages readable as they were
-- sent. The sender's address is never stored raw: ip_hash is an HMAC keyed by APP_KEY
-- (SPEC §6), enough to count one sender's attempts and nothing more.
--
-- page_id is where it was sent from, SET NULL when that page goes: the message is the
-- owner's and outlives the page. read_at is NULL until the owner opens it.
CREATE TABLE form_submissions (
    id {{pk}},
    form_id INTEGER NOT NULL,
    page_id INTEGER NULL,
    data_json TEXT NOT NULL,
    ip_hash VARCHAR(64) NOT NULL,
    read_at VARCHAR(19) NULL,
    created_at VARCHAR(19) NOT NULL,
    CONSTRAINT form_submissions_form_fk FOREIGN KEY (form_id) REFERENCES forms (id) ON DELETE CASCADE,
    CONSTRAINT form_submissions_page_fk FOREIGN KEY (page_id) REFERENCES pages (id) ON DELETE SET NULL
);
CREATE INDEX form_submissions_form_created ON form_submissions (form_id, created_at);
CREATE INDEX form_submissions_ip_created ON form_submissions (ip_hash, created_at);
