/// <reference types="bun-types" />
/**
 * Send a WorkOS AuthKit invitation to a new admin user.
 *
 * AuthKit sign-up is disabled for the admin dashboard, so the only way
 * to onboard a new admin is via this invitation flow:
 *   1. Calls `userManagement.sendInvitation` against the WorkOS API
 *   2. WorkOS sends an email containing a setup link to the address
 *   3. The recipient clicks through, enrolls a passkey (or password)
 *   4. They can then log in via the normal AuthKit flow
 *
 * Prompts for the API key + client ID at runtime — both are sensitive
 * and we don't want them lying around in shell history or env files.
 * The email can be passed as a positional CLI arg (`bun run cf:create-admin
 * user@example.com`) or entered interactively.
 *
 * Defaults to the production WorkOS environment. To target staging,
 * paste the staging API key when prompted.
 *
 * Usage:
 *   bun run cf:create-admin
 *   bun run cf:create-admin user@example.com
 */
import { WorkOS } from '@workos-inc/node';

function ask(question: string, options?: { allowEmpty?: boolean }): string {
  const answer = (prompt(question) ?? '').trim();
  if (!answer && !options?.allowEmpty) {
    throw new Error(`Required value not provided: ${question}`);
  }
  return answer;
}

function isValidEmail(value: string): boolean {
  // RFC-shaped enough for an interactive prompt — WorkOS's API does the
  // canonical validation server-side. The check here just catches typos
  // before we burn an API call.
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
}

async function main(): Promise<void> {
  console.log('Send a WorkOS AuthKit invitation\n');

  const cliEmail = process.argv[2]?.trim();
  const email = cliEmail || ask('Email to invite: ');
  if (!isValidEmail(email)) {
    throw new Error(`"${email}" doesn't look like a valid email address.`);
  }

  console.log(
    '\nProvide WorkOS credentials for the target environment (prod by default).',
  );
  console.log(
    'Find them at https://dashboard.workos.com/ → API Keys (the API key)',
  );
  console.log('and Configuration → Endpoints (the client ID).\n');

  const apiKey = ask('WORKOS_API_KEY (sk_live_... or sk_test_...): ');
  const clientId = ask('WORKOS_CLIENT_ID (client_...): ');

  const orgId = ask('Organization ID (optional, press enter to skip): ', {
    allowEmpty: true,
  });

  const workos = new WorkOS(apiKey, { clientId });

  console.log(`\n→ Sending invitation to ${email}...`);
  try {
    const invitation = await workos.userManagement.sendInvitation({
      email,
      ...(orgId ? { organizationId: orgId } : {}),
    });
    console.log(`✓ Invitation sent`);
    console.log(`  id:         ${invitation.id}`);
    console.log(`  email:      ${invitation.email}`);
    console.log(`  expires at: ${invitation.expiresAt}`);
    if (invitation.acceptedAt) {
      console.log(`  accepted:   ${invitation.acceptedAt}`);
    }
    console.log(
      `\nThe recipient should receive a setup email shortly. If it doesn't`,
    );
    console.log(`arrive, check the WorkOS dashboard → User Management →`);
    console.log(`Invitations to confirm send status and resend if needed.`);
  } catch (err) {
    const msg = err instanceof Error ? err.message : String(err);
    throw new Error(`WorkOS API call failed: ${msg}`);
  }
}

main().catch((err) => {
  console.error('cf-create-admin failed:', err instanceof Error ? err.message : err);
  process.exit(1);
});
