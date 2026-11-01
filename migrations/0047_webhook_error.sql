-- A MESSAGE ITS FORM'S WEB ADDRESS DID NOT TAKE (PLAN.md O-77, the owner, 2026-10-22: "možemo li
-- dodati gumb za ručno slanje hooka ako nije prošao pri submitu").
--
-- Why the last attempt failed, in words for the owner; NULL when it was delivered, or when the form
-- sends to no address. A message is kept with this set even where its form keeps nothing (D-269),
-- so Send again has something to send; it is deleted once delivered, or after thirty days.
ALTER TABLE form_submissions ADD COLUMN webhook_error VARCHAR(500) NULL;
