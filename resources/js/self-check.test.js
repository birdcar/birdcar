import { expect, test } from 'bun:test';
import { rankPatterns, summarize, tally } from './self-check';

const patterns = ['remembering', 'typed-twice', 'numbers', 'your-yes'];
const names = { remembering: 'It runs on remembering', 'typed-twice': 'The same information, typed twice', numbers: 'Nobody trusts the numbers', 'your-yes': 'Waiting on your yes' };

test('only ticked signs count toward their pattern', () => {
    const inputs = [
        { checked: true, dataset: { pattern: 'your-yes' } },
        { checked: true, dataset: { pattern: 'your-yes' } },
        { checked: false, dataset: { pattern: 'numbers' } },
        { checked: true, dataset: { pattern: 'remembering' } },
    ];

    expect(tally(inputs)).toEqual({ 'your-yes': 2, remembering: 1 });
});

test('the most recognized patterns rise and ties keep the guide order', () => {
    expect(rankPatterns(patterns, { 'your-yes': 2, remembering: 1, numbers: 1 })).toEqual(['your-yes', 'remembering', 'numbers', 'typed-twice']);
    expect(rankPatterns(patterns, {})).toEqual(patterns);
});

test('the summary names at most the two most familiar patterns and nothing before a tick', () => {
    expect(summarize(patterns, {}, names)).toBe('');
    expect(summarize(['numbers', 'remembering', 'your-yes'], { numbers: 3, remembering: 1, 'your-yes': 1 }, names))
        .toBe('Sounds most like: Nobody trusts the numbers and It runs on remembering.');
    expect(summarize(['your-yes', 'numbers'], { 'your-yes': 1 }, names)).toBe('Sounds most like: Waiting on your yes.');
});
