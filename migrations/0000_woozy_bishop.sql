CREATE TABLE `leads` (
	`id` text PRIMARY KEY NOT NULL,
	`submitted_at` text NOT NULL,
	`name` text NOT NULL,
	`email` text NOT NULL,
	`message` text NOT NULL,
	`user_agent` text,
	`source` text DEFAULT 'birdcar.dev/contact' NOT NULL,
	`status` text DEFAULT 'pending' NOT NULL,
	`category` text,
	`qualification` text,
	`score` integer,
	`draft` text,
	`outcome` text,
	`responded_at` text,
	`created_at` text DEFAULT (datetime('now')) NOT NULL,
	`updated_at` text DEFAULT (datetime('now')) NOT NULL
);
--> statement-breakpoint
CREATE INDEX `idx_leads_status` ON `leads` (`status`);--> statement-breakpoint
CREATE INDEX `idx_leads_submitted` ON `leads` (`submitted_at`);