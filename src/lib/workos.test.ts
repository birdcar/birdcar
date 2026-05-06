import { describe, test, expect } from 'bun:test';
import {
  buildClearedSessionCookie,
  buildSessionCookie,
  readSessionCookie,
  safeReturnPath,
} from './workos';

describe('safeReturnPath', () => {
  test('passes a clean absolute admin path through', () => {
    expect(safeReturnPath('/admin/leads')).toBe('/admin/leads');
  });

  test('passes a deeper path with a query string', () => {
    expect(safeReturnPath('/admin/leads/L1?from=index')).toBe(
      '/admin/leads/L1?from=index',
    );
  });

  test('falls back to /admin/leads when input is null', () => {
    expect(safeReturnPath(null)).toBe('/admin/leads');
  });

  test('falls back when input is undefined', () => {
    expect(safeReturnPath(undefined)).toBe('/admin/leads');
  });

  test('falls back on empty string', () => {
    expect(safeReturnPath('')).toBe('/admin/leads');
  });

  test('rejects protocol-relative input that opens redirect to other host', () => {
    expect(safeReturnPath('//evil.com/path')).toBe('/admin/leads');
  });

  test('rejects backslash variants browsers may normalize to //', () => {
    expect(safeReturnPath('/\\evil.com')).toBe('/admin/leads');
  });

  test('rejects a full URL', () => {
    expect(safeReturnPath('https://evil.com/path')).toBe('/admin/leads');
  });

  test('rejects relative paths that do not start with /', () => {
    expect(safeReturnPath('admin/leads')).toBe('/admin/leads');
  });

  test('rejects header injection via CR', () => {
    expect(safeReturnPath('/admin\rLocation: http://evil.com')).toBe(
      '/admin/leads',
    );
  });

  test('rejects header injection via LF', () => {
    expect(safeReturnPath('/admin\nSet-Cookie: x=1')).toBe('/admin/leads');
  });
});

describe('readSessionCookie', () => {
  test('extracts wos-session value when it is the only cookie', () => {
    const req = new Request('http://x', {
      headers: { cookie: 'wos-session=ABC123' },
    });
    expect(readSessionCookie(req)).toBe('ABC123');
  });

  test('extracts wos-session from a multi-cookie header', () => {
    const req = new Request('http://x', {
      headers: { cookie: 'foo=1; wos-session=ABC123; bar=2' },
    });
    expect(readSessionCookie(req)).toBe('ABC123');
  });

  test('extracts when wos-session is the trailing cookie', () => {
    const req = new Request('http://x', {
      headers: { cookie: 'foo=1; bar=2; wos-session=END' },
    });
    expect(readSessionCookie(req)).toBe('END');
  });

  test('returns undefined when the cookie header is missing', () => {
    const req = new Request('http://x');
    expect(readSessionCookie(req)).toBeUndefined();
  });

  test('returns undefined when the cookie header is present but lacks wos-session', () => {
    const req = new Request('http://x', { headers: { cookie: 'foo=1; bar=2' } });
    expect(readSessionCookie(req)).toBeUndefined();
  });

  test('does not match a substring of another cookie name', () => {
    const req = new Request('http://x', {
      headers: { cookie: 'not-wos-session=NO; wos-session=YES' },
    });
    expect(readSessionCookie(req)).toBe('YES');
  });
});

describe('buildSessionCookie', () => {
  test('omits Secure on http://localhost', () => {
    const cookie = buildSessionCookie('VAL', new URL('http://localhost:4321/admin'));
    expect(cookie).not.toContain('Secure');
    expect(cookie).toContain('wos-session=VAL');
    expect(cookie).toContain('HttpOnly');
    expect(cookie).toContain('SameSite=Lax');
    expect(cookie).toMatch(/Max-Age=\d+/);
  });

  test('omits Secure on http://127.0.0.1', () => {
    const cookie = buildSessionCookie('VAL', new URL('http://127.0.0.1:4321/'));
    expect(cookie).not.toContain('Secure');
  });

  test('includes Secure on any https origin', () => {
    const cookie = buildSessionCookie('VAL', new URL('https://birdcar.dev/'));
    expect(cookie).toContain('Secure');
  });

  test('includes Secure on http origin that is not localhost', () => {
    const cookie = buildSessionCookie('VAL', new URL('http://staging.internal/'));
    expect(cookie).toContain('Secure');
  });
});

describe('buildClearedSessionCookie', () => {
  test('emits Max-Age=0 to clear the cookie', () => {
    const cookie = buildClearedSessionCookie(new URL('https://birdcar.dev/'));
    expect(cookie).toContain('wos-session=');
    expect(cookie).toContain('Max-Age=0');
  });

  test('matches the Secure flag rules from buildSessionCookie', () => {
    const local = buildClearedSessionCookie(new URL('http://localhost:4321/'));
    expect(local).not.toContain('Secure');
    const prod = buildClearedSessionCookie(new URL('https://birdcar.dev/'));
    expect(prod).toContain('Secure');
  });
});
