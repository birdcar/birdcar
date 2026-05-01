-- Rename the `consulting-offfit` category to `consulting-off-fit`. The
-- column has no CHECK constraint (drizzle's `enum` is type-level only),
-- so this is a plain UPDATE — but skipping it would leave any existing
-- rows with values that no longer match the TypeScript enum, which
-- breaks downstream code that narrows on the union.
UPDATE leads SET category = 'consulting-off-fit' WHERE category = 'consulting-offfit';
