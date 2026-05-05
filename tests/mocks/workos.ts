// Phase 1 skeleton — Phase 2 will use this via vi.mock('@workos-inc/node', ...)
// to short-circuit the WorkOS SDK without hitting workos.com.
//
// Surfaces matched: getAuthorizationUrl, authenticateWithCode,
// loadSealedSession().{ authenticate, refresh, getLogoutUrl }.
import type { SessionUser } from '../../src/lib/workos';

export interface MockWorkOSOptions {
  user?: Partial<SessionUser>;
  sealedSession?: string;
  refreshedSession?: string;
  /** When true, `authenticate()` resolves authenticated; refresh path skipped. */
  authenticated?: boolean;
  /** When true, refresh succeeds with refreshedSession. */
  refreshable?: boolean;
}

const DEFAULT_USER: SessionUser = {
  id: 'user_test',
  email: 'tester@example.com',
  firstName: 'Test',
  lastName: 'User',
};

export function buildWorkOSMock(overrides: MockWorkOSOptions = {}) {
  const user = { ...DEFAULT_USER, ...overrides.user };
  const sealedSession = overrides.sealedSession ?? 'sealed.test.session';
  const refreshedSession = overrides.refreshedSession ?? 'sealed.refreshed.session';
  const authenticated = overrides.authenticated ?? true;
  const refreshable = overrides.refreshable ?? true;

  return {
    WorkOS: class {
      userManagement = {
        getAuthorizationUrl: ({ state }: { state: string }) =>
          `https://api.workos.com/user_management/authorize?state=${encodeURIComponent(state)}`,
        authenticateWithCode: async () => ({ user, sealedSession }),
        loadSealedSession: () => ({
          authenticate: async () =>
            authenticated
              ? { authenticated: true, user }
              : { authenticated: false },
          refresh: async () =>
            refreshable
              ? { authenticated: true, user, sealedSession: refreshedSession }
              : { authenticated: false },
          getLogoutUrl: async () => 'https://api.workos.com/user_management/logout',
        }),
      };
    },
  };
}
