import { describe, test, expect, spyOn, afterEach } from 'bun:test';
import { logEvent, logWarn, logError } from './event-log';

const consoleLog = spyOn(console, 'log').mockImplementation(() => {});
const consoleWarn = spyOn(console, 'warn').mockImplementation(() => {});
const consoleError = spyOn(console, 'error').mockImplementation(() => {});

afterEach(() => {
  consoleLog.mockClear();
  consoleWarn.mockClear();
  consoleError.mockClear();
});

describe('logEvent', () => {
  test('writes a payload with event key and merged context', () => {
    logEvent('lead.received', { leadId: 'L1', source: 'web' });
    expect(consoleLog).toHaveBeenCalledTimes(1);
    expect(consoleLog).toHaveBeenCalledWith({
      event: 'lead.received',
      leadId: 'L1',
      source: 'web',
    });
  });

  test('omits context when none is supplied', () => {
    logEvent('cron.scheduled.fired');
    expect(consoleLog).toHaveBeenCalledWith({ event: 'cron.scheduled.fired' });
  });

  test('does not call console.warn or console.error', () => {
    logEvent('lead.persisted', { leadId: 'L1' });
    expect(consoleWarn).not.toHaveBeenCalled();
    expect(consoleError).not.toHaveBeenCalled();
  });
});

describe('logWarn', () => {
  test('writes payload without error key when err is omitted', () => {
    logWarn('notify-nick.skipped', { leadId: 'L2' });
    expect(consoleWarn).toHaveBeenCalledWith({
      event: 'notify-nick.skipped',
      leadId: 'L2',
    });
  });

  test('attaches errorFields when err is provided', () => {
    logWarn('lead.enqueue.failed', { leadId: 'L3' }, new Error('queue offline'));
    const call = consoleWarn.mock.calls[0]?.[0] as Record<string, unknown>;
    expect(call.event).toBe('lead.enqueue.failed');
    expect(call.leadId).toBe('L3');
    const error = call.error as { name: string; message: string };
    expect(error.name).toBe('Error');
    expect(error.message).toBe('queue offline');
  });
});

describe('logError', () => {
  test('always attaches errorFields under the error key', () => {
    logError('agent.queueLead.failed', { leadId: 'L4' }, new Error('boom'));
    const call = consoleError.mock.calls[0]?.[0] as Record<string, unknown>;
    expect(call.event).toBe('agent.queueLead.failed');
    expect(call.leadId).toBe('L4');
    const error = call.error as { name: string; message: string };
    expect(error.message).toBe('boom');
  });

  test('coerces a non-Error throw via errorFields', () => {
    logError('admin.callback.failed', {}, 'string thrown');
    const call = consoleError.mock.calls[0]?.[0] as Record<string, unknown>;
    const error = call.error as { name: string; message: string };
    expect(error.name).toBe('Unknown');
    expect(error.message).toBe('string thrown');
  });
});
