import { sql } from 'drizzle-orm';
import { sqliteTable, text, integer, index } from 'drizzle-orm/sqlite-core';

export const leads = sqliteTable(
  'leads',
  {
    id: text('id').primaryKey(),
    submittedAt: text('submitted_at').notNull(),
    name: text('name').notNull(),
    email: text('email').notNull(),
    message: text('message').notNull(),
    userAgent: text('user_agent'),
    source: text('source').notNull().default('birdcar.dev/contact'),
    status: text('status', {
      // `awaiting-approval` is the dwell state between the workflow's
      // `notify-nick` step and `waitForApproval` returning. The sweep
      // skips it — these rows are deliberately stalled on a human, not a
      // failure mode. Phase 2 dashboard transitions them to `done`/`discarded`.
      enum: ['pending', 'processing', 'awaiting-approval', 'done', 'discarded'],
    })
      .notNull()
      .default('pending'),
    category: text('category', {
      enum: [
        'consulting-fit',
        'consulting-offfit',
        'support-question',
        'vendor-pitch',
        'recruiting',
        'spam',
      ],
    }),
    qualification: text('qualification'),
    score: integer('score'),
    draft: text('draft'),
    outcome: text('outcome', {
      enum: ['approved', 'edited', 'discarded'],
    }),
    respondedAt: text('responded_at'),
    createdAt: text('created_at')
      .notNull()
      .default(sql`(datetime('now'))`),
    updatedAt: text('updated_at')
      .notNull()
      .default(sql`(datetime('now'))`),
  },
  (table) => [
    index('idx_leads_status').on(table.status),
    index('idx_leads_submitted').on(table.submittedAt),
  ],
);

export type Lead = typeof leads.$inferSelect;
export type NewLead = typeof leads.$inferInsert;
