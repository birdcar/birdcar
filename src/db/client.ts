import { drizzle, type DrizzleD1Database } from 'drizzle-orm/d1';
import type { D1Database } from '@cloudflare/workers-types';
import * as schema from './schema';

export type Database = DrizzleD1Database<typeof schema>;

export function getDb(d1: D1Database): Database {
  return drizzle(d1, { schema, logger: false });
}
