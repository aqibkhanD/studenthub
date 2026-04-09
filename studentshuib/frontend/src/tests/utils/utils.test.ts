import { describe, it, expect } from 'vitest';
import { toFieldKey, formatStatus, truncate, relativeTime } from '@/lib/utils';

// ============================================================
// toFieldKey
// ============================================================

describe('toFieldKey', () => {
  it('converts a simple two-word label to snake_case', () => {
    expect(toFieldKey('Student Name')).toBe('student_name');
  });

  it('lowercases all characters', () => {
    expect(toFieldKey('FULL NAME')).toBe('full_name');
  });

  it('strips punctuation', () => {
    expect(toFieldKey("Father's Name")).toBe('fathers_name');
  });

  it('strips leading numbers', () => {
    expect(toFieldKey('123 Bad Start')).toBe('bad_start');
  });

  it('collapses multiple spaces into a single underscore', () => {
    expect(toFieldKey('first   last   name')).toBe('first_last_name');
  });

  it('trims leading and trailing whitespace', () => {
    expect(toFieldKey('  email  ')).toBe('email');
  });

  it('handles a single word', () => {
    expect(toFieldKey('Email')).toBe('email');
  });

  it('removes special characters that are not alphanumeric or spaces', () => {
    expect(toFieldKey('Hello, World!')).toBe('hello_world');
  });

  it('returns empty string for an all-numeric label', () => {
    expect(toFieldKey('123')).toBe('');
  });

  it('handles an empty string', () => {
    expect(toFieldKey('')).toBe('');
  });
});

// ============================================================
// formatStatus
// ============================================================

describe('formatStatus', () => {
  it('formats in_review to "In Review"', () => {
    expect(formatStatus('in_review')).toBe('In Review');
  });

  it('formats submitted to "Submitted"', () => {
    expect(formatStatus('submitted')).toBe('Submitted');
  });

  it('formats action_required to "Action Required"', () => {
    expect(formatStatus('action_required')).toBe('Action Required');
  });

  it('handles single-word statuses', () => {
    expect(formatStatus('approved')).toBe('Approved');
  });
});

// ============================================================
// truncate
// ============================================================

describe('truncate', () => {
  it('returns the original string when within limit', () => {
    expect(truncate('Hello', 10)).toBe('Hello');
  });

  it('truncates to maxLen and appends ellipsis', () => {
    const result = truncate('Hello, World!', 5);
    expect(result).toBe('Hello…');
    expect(result.length).toBe(6); // 5 chars + ellipsis
  });

  it('returns the string unchanged when exactly at limit', () => {
    expect(truncate('Hello', 5)).toBe('Hello');
  });

  it('handles empty string', () => {
    expect(truncate('', 10)).toBe('');
  });
});

// ============================================================
// relativeTime
// ============================================================

describe('relativeTime', () => {
  it('returns "just now" for dates less than 1 minute ago', () => {
    const now = new Date(Date.now() - 30_000).toISOString(); // 30s ago
    expect(relativeTime(now)).toBe('just now');
  });

  it('returns minutes for dates within the last hour', () => {
    const fiveMinAgo = new Date(Date.now() - 5 * 60_000).toISOString();
    expect(relativeTime(fiveMinAgo)).toBe('5m ago');
  });

  it('returns hours for dates within the last day', () => {
    const threeHoursAgo = new Date(Date.now() - 3 * 60 * 60_000).toISOString();
    expect(relativeTime(threeHoursAgo)).toBe('3h ago');
  });

  it('returns days for dates within the last week', () => {
    const twoDaysAgo = new Date(Date.now() - 2 * 24 * 60 * 60_000).toISOString();
    expect(relativeTime(twoDaysAgo)).toBe('2d ago');
  });

  it('returns a locale date string for dates older than 7 days', () => {
    const oldDate = new Date(Date.now() - 10 * 24 * 60 * 60_000).toISOString();
    const result  = relativeTime(oldDate);
    // Just verify it's not one of the relative formats
    expect(result).not.toContain('ago');
    expect(result).not.toBe('just now');
  });
});
