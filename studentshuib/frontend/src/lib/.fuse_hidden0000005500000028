/**
 * Shared utility functions used across the frontend.
 */

/**
 * Convert a human-readable label into a snake_case field key suitable
 * for use as a form field identifier.
 *
 * Examples:
 *   "Student Name"   → "student_name"
 *   "Email Address"  → "email_address"
 *   "123 Bad Start"  → "bad_start"   (leading non-alpha chars stripped)
 *   "Hello, World!"  → "hello_world" (punctuation removed)
 */
export function toFieldKey(label: string): string {
  return label
    .toLowerCase()
    .replace(/[^a-z0-9\s]/g, '')
    .trim()
    .replace(/\s+/g, '_')
    .replace(/^[^a-z]+/, '');
}

/**
 * Format a status string from snake_case to Title Case with spaces.
 * e.g.  "in_review" → "In Review"
 */
export function formatStatus(status: string): string {
  return status
    .split('_')
    .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
    .join(' ');
}

/**
 * Truncate a string to maxLen characters, appending '…' if truncated.
 */
export function truncate(text: string, maxLen: number): string {
  if (text.length <= maxLen) return text;
  return text.slice(0, maxLen) + '…';
}

/**
 * Return a relative time string for display (e.g. "2 hours ago").
 * Falls back to the full date string for older dates.
 */
export function relativeTime(dateStr: string): string {
  const date = new Date(dateStr);
  const now  = new Date();
  const diffMs   = now.getTime() - date.getTime();
  const diffMins = Math.floor(diffMs / 60_000);

  if (diffMins < 1)    return 'just now';
  if (diffMins < 60)   return `${diffMins}m ago`;
  const diffHours = Math.floor(diffMins / 60);
  if (diffHours < 24)  return `${diffHours}h ago`;
  const diffDays = Math.floor(diffHours / 24);
  if (diffDays < 7)    return `${diffDays}d ago`;
  return date.toLocaleDateString();
}
