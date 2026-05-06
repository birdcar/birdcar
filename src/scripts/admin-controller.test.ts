import { describe, test, expect, beforeAll, afterAll, beforeEach } from 'bun:test';
import { Window } from 'happy-dom';
import { setButtonsDisabled, showNotice } from './admin-controller';

// happy-dom 20 dropped GlobalRegistrator; the supported entry point is to
// instantiate Window and bind the document/HTMLElement globals manually.
let window: Window;

beforeAll(() => {
  window = new Window({ url: 'http://localhost:4321/admin' });
  // happy-dom's QuerySelector internals call `new this.window.SyntaxError(...)`
  // when validating selectors; the bundled Window doesn't expose that
  // constructor by default. Patch it from the host runtime.
  (window as unknown as { SyntaxError: typeof SyntaxError }).SyntaxError = SyntaxError;
  // Mirror the parts of the BOM that the controller and assertions reach for.
  // `Element`/`HTMLElement` constructors are needed for `instanceof` checks.
  (globalThis as any).window = window;
  (globalThis as any).document = window.document;
  (globalThis as any).HTMLElement = window.HTMLElement;
  (globalThis as any).HTMLButtonElement = window.HTMLButtonElement;
  (globalThis as any).Element = window.Element;
  (globalThis as any).Node = window.Node;
});

afterAll(async () => {
  await window.happyDOM.close();
  delete (globalThis as any).window;
  delete (globalThis as any).document;
  delete (globalThis as any).HTMLElement;
  delete (globalThis as any).HTMLButtonElement;
  delete (globalThis as any).Element;
  delete (globalThis as any).Node;
});

beforeEach(() => {
  // Reset the document body between tests so notices/buttons don't leak.
  while (document.body.firstChild) {
    document.body.removeChild(document.body.firstChild);
  }
});

function makeScope(buttonIds: string[] = []): HTMLElement {
  const scope = document.createElement('div');
  scope.id = 'scope';
  for (const id of buttonIds) {
    const button = document.createElement('button');
    button.id = id;
    button.textContent = id;
    scope.appendChild(button);
  }
  document.body.appendChild(scope);
  return scope as unknown as HTMLElement;
}

describe('setButtonsDisabled', () => {
  test('disables every button inside the scope', () => {
    const scope = makeScope(['a', 'b']);
    setButtonsDisabled(scope, true);
    const a = document.getElementById('a') as unknown as HTMLButtonElement;
    const b = document.getElementById('b') as unknown as HTMLButtonElement;
    expect(a.disabled).toBe(true);
    expect(b.disabled).toBe(true);
  });

  test('re-enables buttons when called with disabled=false', () => {
    const scope = makeScope(['a']);
    setButtonsDisabled(scope, true);
    setButtonsDisabled(scope, false);
    const button = scope.querySelector('button') as unknown as HTMLButtonElement;
    expect(button.disabled).toBe(false);
  });

  test('only touches buttons within the scope', () => {
    const outside = document.createElement('button');
    outside.id = 'outside';
    document.body.appendChild(outside);
    const scope = makeScope(['inside']);
    setButtonsDisabled(scope, true);
    const inside = document.getElementById('inside') as unknown as HTMLButtonElement;
    expect((outside as unknown as HTMLButtonElement).disabled).toBe(false);
    expect(inside.disabled).toBe(true);
  });
});

describe('showNotice', () => {
  test('inserts a paragraph before the scope on first call', () => {
    const scope = makeScope();
    showNotice(scope, 'Lead is no longer pending.');
    const notice = document.getElementById('lead-status-notice') as unknown as HTMLElement;
    expect(notice).not.toBeNull();
    expect(notice.tagName).toBe('P');
    expect(notice.textContent).toBe('Lead is no longer pending.');
    expect(notice.className).toBe('bc-admin-notice');
    expect(notice.nextElementSibling).toBe(scope as unknown as Element);
  });

  test('does not insert a duplicate when one with the same id exists', () => {
    const scope = makeScope();
    showNotice(scope, 'first');
    showNotice(scope, 'second');
    const notices = document.querySelectorAll('p.bc-admin-notice');
    expect(notices.length).toBe(1);
    expect((notices[0] as unknown as HTMLElement).textContent).toBe('first');
  });

  test('honors a custom id parameter', () => {
    const scope = makeScope();
    showNotice(scope, 'one', 'first-notice');
    showNotice(scope, 'two', 'second-notice');
    expect(document.getElementById('first-notice')).not.toBeNull();
    expect(document.getElementById('second-notice')).not.toBeNull();
  });
});
