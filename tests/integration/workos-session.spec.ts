import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { env } from 'cloudflare:workers';
import {
  buildClearedSessionCookie,
  buildSessionCookie,
  readSessionCookie,
  safeReturnPath,
  validateSession,
} from '../../src/lib/workos';

// vi.mock is hoisted to the top of the file. The factory captures `mockState`
// via closure so individual tests mutate state.authenticated / state.refreshable
// rather than re-mocking. This avoids the vi.doMock + dynamic-import cache
// hazard where the module under test holds the unmocked SDK reference.
const { mockState } = vi.hoisted(() => ({
  mockState: { authenticated: true, refreshable: true },
}));

vi.mock('@workos-inc/node', () => ({
  WorkOS: class {
    userManagement = {
      getAuthorizationUrl: ({ state }: { state: string }) =>
        `https://api.workos.com/user_management/authorize?state=${encodeURIComponent(state)}`,
      authenticateWithCode: async () => ({
        user: {
          id: 'user_test',
          email: 'tester@example.com',
          firstName: 'Test',
          lastName: 'User',
        },
        sealedSession: 'sealed.test.session',
      }),
      loadSealedSession: () => ({
        authenticate: async () =>
          mockState.authenticated
            ? {
                authenticated: true,
                user: {
                  id: 'user_test',
                  email: 'tester@example.com',
                  firstName: 'Test',
                  lastName: 'User',
                },
              }
            : { authenticated: false },
        refresh: async () =>
          mockState.refreshable
            ? {
                authenticated: true,
                user: {
                  id: 'user_test',
                  email: 'tester@example.com',
                  firstName: 'Test',
                  lastName: 'User',
                },
                sealedSession: 'sealed.refreshed.session',
              }
            : { authenticated: false },
        getLogoutUrl: async () =>
          'https://api.workos.com/user_management/logout',
      }),
    };
  },
}));

beforeEach(() => {
  // Reset to the defaults each test to avoid order-dependence.
  mockState.authenticated = true;
  mockState.refreshable = true;
});

afterEach(() => {
  vi.restoreAllMocks();
});

describe('safeReturnPath', () => {
  it('passes a clean absolute path through unchanged', () => {
    expect(safeReturnPath('/admin/leads')).toBe('/admin/leads');
  });

  it('falls back when value is null or undefined', () => {
    expect(safeReturnPath(null)).toBe('/admin/leads');
    expect(safeReturnPath(undefined)).toBe('/admin/leads');
  });

  it('falls back when value is an empty string', () => {
    expect(safeReturnPath('')).toBe('/admin/leads');
  });

  it('rejects protocol-relative URLs (open-redirect vector)', () => {
    expect(safeReturnPath('//evil.com/path')).toBe('/admin/leads');
  });

  it('rejects backslash variants browsers normalize to //', () => {
    expect(safeReturnPath('/\\evil.com')).toBe('/admin/leads');
  });

  it('rejects paths containing CR or LF (header-injection vector)', () => {
    expect(safeReturnPath('/admin\nLocation: x')).toBe('/admin/leads');
    expect(safeReturnPath('/admin\rLocation: x')).toBe('/admin/leads');
  });

  it('rejects values that do not start with /', () => {
    expect(safeReturnPath('admin/leads')).toBe('/admin/leads');
    expect(safeReturnPath('https://evil.com/path')).toBe('/admin/leads');
  });
});

describe('readSessionCookie', () => {
  it('returns undefined when no cookie header is present', () => {
    const req = new Request('http://test/');
    expect(readSessionCookie(req)).toBeUndefined();
  });

  it('extracts wos-session from a single-cookie header', () => {
    const req = new Request('http://test/', {
      headers: { cookie: 'wos-session=ABC123' },
    });
    expect(readSessionCookie(req)).toBe('ABC123');
  });

  it('extracts wos-session from a multi-cookie header', () => {
    const req = new Request('http://test/', {
      headers: { cookie: 'foo=1; wos-session=ABC123; bar=2' },
    });
    expect(readSessionCookie(req)).toBe('ABC123');
  });

  it('returns undefined when the cookie header has other cookies but no wos-session', () => {
    const req = new Request('http://test/', {
      headers: { cookie: 'foo=1; bar=2' },
    });
    expect(readSessionCookie(req)).toBeUndefined();
  });
});

describe('buildSessionCookie Secure flag rules', () => {
  it('emits Secure on https origins', () => {
    const cookie = buildSessionCookie('VAL', new URL('https://www.birdcar.dev/admin/leads'));
    expect(cookie).toContain('Secure');
  });

  it('omits Secure on http localhost', () => {
    const cookie = buildSessionCookie('VAL', new URL('http://localhost:4321/admin/leads'));
    expect(cookie).not.toContain('Secure');
  });

  it('omits Secure on http 127.0.0.1', () => {
    const cookie = buildSessionCookie('VAL', new URL('http://127.0.0.1:4321/admin/leads'));
    expect(cookie).not.toContain('Secure');
  });

  it('emits Secure on http non-loopback origins (defense in depth)', () => {
    const cookie = buildSessionCookie('VAL', new URL('http://example.com/admin/leads'));
    expect(cookie).toContain('Secure');
  });

  it('always sets HttpOnly and SameSite=Lax', () => {
    const cookie = buildSessionCookie('VAL', new URL('https://www.birdcar.dev/'));
    expect(cookie).toContain('HttpOnly');
    expect(cookie).toContain('SameSite=Lax');
  });

  it('uses Path=/ so the cookie applies site-wide', () => {
    const cookie = buildSessionCookie('VAL', new URL('https://www.birdcar.dev/'));
    expect(cookie).toContain('Path=/');
  });
});

describe('buildClearedSessionCookie', () => {
  it('emits Max-Age=0 to clear the cookie', () => {
    const cookie = buildClearedSessionCookie(new URL('https://www.birdcar.dev/'));
    expect(cookie).toContain('Max-Age=0');
  });
});

describe('validateSession', () => {
  it('returns null when no cookie is provided', async () => {
    const result = await validateSession(env, undefined);
    expect(result).toBeNull();
  });

  it('returns the user when authenticate succeeds', async () => {
    mockState.authenticated = true;
    const result = await validateSession(env, 'sealed.session.user_test');
    expect(result?.user.email).toBe('tester@example.com');
    expect(result?.refreshedCookie).toBeUndefined();
  });

  it('refreshes and returns a refreshedCookie when authenticate fails but refresh succeeds', async () => {
    mockState.authenticated = false;
    mockState.refreshable = true;
    const result = await validateSession(env, 'sealed.session.user_test');
    expect(result?.user.email).toBe('tester@example.com');
    expect(result?.refreshedCookie).toBe('sealed.refreshed.session');
  });

  it('returns null when both authenticate and refresh fail', async () => {
    mockState.authenticated = false;
    mockState.refreshable = false;
    const result = await validateSession(env, 'sealed.session.user_test');
    expect(result).toBeNull();
  });
});
