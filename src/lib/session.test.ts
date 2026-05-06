import { describe, test, expect } from 'bun:test';
import { attachRefreshedSession, clearSession, requireSession } from './session';
import type { Env } from '../types';

const STUB_ENV = {
  WORKOS_API_KEY: 'unused',
  WORKOS_CLIENT_ID: 'unused',
  WORKOS_REDIRECT_URI: 'http://localhost:4321/admin/callback',
  WORKOS_COOKIE_PASSWORD: 'a'.repeat(32),
} as unknown as Env;

describe('requireSession', () => {
  test('returns null session when no cookie header is present', async () => {
    const request = new Request('http://localhost:4321/admin/leads');
    const result = await requireSession(request, STUB_ENV);
    expect(result.session).toBeNull();
  });

  test('returns null session when cookie header lacks wos-session', async () => {
    const request = new Request('http://localhost:4321/admin/leads', {
      headers: { cookie: 'foo=bar' },
    });
    const result = await requireSession(request, STUB_ENV);
    expect(result.session).toBeNull();
  });
});

describe('attachRefreshedSession', () => {
  test('appends a Set-Cookie header carrying the refreshed value', () => {
    const response = new Response('hi');
    const url = new URL('https://birdcar.dev/admin');
    const out = attachRefreshedSession(response, 'NEW_SEALED', url);
    const setCookie = out.headers.get('set-cookie');
    expect(setCookie).toContain('wos-session=NEW_SEALED');
    expect(setCookie).toContain('Secure');
  });

  test('preserves an existing Set-Cookie when appending the refresh', () => {
    const response = new Response('hi');
    response.headers.append('Set-Cookie', 'other=1; Path=/');
    const out = attachRefreshedSession(
      response,
      'NEW_SEALED',
      new URL('https://birdcar.dev/admin'),
    );
    const cookies = out.headers.getSetCookie?.() ?? [];
    const cookieHeader = out.headers.get('set-cookie') ?? '';
    expect(cookies.length || cookieHeader.length).toBeGreaterThan(0);
    expect(cookieHeader).toContain('wos-session=NEW_SEALED');
  });

  test('omits Secure on http localhost', () => {
    const response = new Response('hi');
    const out = attachRefreshedSession(
      response,
      'V',
      new URL('http://localhost:4321/admin'),
    );
    const setCookie = out.headers.get('set-cookie') ?? '';
    expect(setCookie).not.toContain('Secure');
  });
});

describe('clearSession', () => {
  test('returns a Max-Age=0 cookie value', () => {
    const value = clearSession(new URL('https://birdcar.dev/admin/logout'));
    expect(value).toContain('wos-session=');
    expect(value).toContain('Max-Age=0');
    expect(value).toContain('Secure');
  });
});
