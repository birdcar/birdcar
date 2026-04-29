-- The schema's `updated_at DEFAULT (datetime('now'))` only fires on INSERT.
-- Without this trigger, every UPDATE leaves updated_at stale, which in
-- turn makes the agent's sweep think every in-flight `processing` row has
-- stalled and reset it to `pending` — respawning the workflow on top of
-- the one already running. The trigger ensures updated_at always reflects
-- the most recent change to the row, so the sweep's cutoff comparison
-- distinguishes real stalls from active progress.
CREATE TRIGGER IF NOT EXISTS trg_leads_bump_updated_at
AFTER UPDATE ON leads
FOR EACH ROW
WHEN OLD.updated_at IS NEW.updated_at
BEGIN
  UPDATE leads SET updated_at = datetime('now') WHERE id = NEW.id;
END;
