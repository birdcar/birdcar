import { expect, test } from 'bun:test';
import { describeResults, matchesQuery } from './find-essay';

const essay = 'your metrics are bullshit breaking the doom cycle of support metrics with service level thinking';

test('an essay matches when every word of the query appears, in any order or case', () => {
    expect(matchesQuery(essay, 'Support METRICS')).toBe(true);
    expect(matchesQuery(essay, 'metrics ai')).toBe(false);
});

test('an empty query matches every essay', () => {
    expect(matchesQuery(essay, '')).toBe(true);
    expect(matchesQuery(essay, '   ')).toBe(true);
});

test('the status names the count only while searching', () => {
    expect(describeResults(10, '')).toBe('');
    expect(describeResults(1, 'bug')).toBe('1 essay matches.');
    expect(describeResults(0, 'zebra')).toBe('0 essays match.');
});
