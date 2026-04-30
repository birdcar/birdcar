-- Lead-level idempotency for the notify-nick email. Without it, every
-- workflow respawn sends a fresh email — the user got 83 notifications
-- for a single lead during the sweep-thrash window. notify-nick now
-- claims the slot via an atomic UPDATE WHERE notified_at IS NULL, sends
-- only if the claim succeeded, and rolls the claim back on send failure
-- so retries can re-attempt.
ALTER TABLE leads ADD COLUMN notified_at text;
