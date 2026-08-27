/**
 * Number formatting for the panels.
 *
 * A debug bar is read by scanning it, so every figure is shown at a precision
 * you can compare at a glance: three significant figures, and a unit chosen so
 * the number in front of it is between 1 and 999.
 */

const SECOND = 1;
const MILLISECOND = 0.001;

/**
 * A duration in seconds, as a human-readable string.
 */
export function duration(seconds: number): string {
  if (!Number.isFinite(seconds) || seconds < 0) return '—';

  if (seconds >= SECOND) return `${round(seconds)} s`;
  if (seconds >= MILLISECOND) return `${round(seconds * 1000)} ms`;

  // Anything faster than a millisecond is noise as an individual figure, but
  // rounding it to `0 ms` makes a list of them look broken.
  return `${round(seconds * 1_000_000)} µs`;
}

/**
 * A byte count, in binary units, as PHP's `memory_get_peak_usage` reports it.
 */
export function bytes(value: number): string {
  if (!Number.isFinite(value) || value < 0) return '—';

  const units = ['B', 'KiB', 'MiB', 'GiB', 'TiB'];

  let magnitude = 0;

  while (value >= 1024 && magnitude < units.length - 1) {
    value /= 1024;
    magnitude++;
  }

  return `${round(value)} ${units[magnitude]}`;
}

export function percentage(fraction: number): string {
  return `${Math.round(fraction * 100)}%`;
}

/**
 * A count, with thousands separated so a four-figure query count is
 * recognisable as one at a glance.
 */
export function count(value: number): string {
  return value.toLocaleString();
}

/**
 * A wall-clock time, for the request picker.
 */
export function time(unixSeconds: number): string {
  return new Date(unixSeconds * 1000).toLocaleTimeString();
}

/**
 * Three significant figures, without a trailing `.0` on whole numbers.
 */
function round(value: number): string {
  const decimals = value >= 100 ? 0 : value >= 10 ? 1 : 2;

  return String(Number(value.toFixed(decimals)));
}

/**
 * The band an HTTP status falls into, for colouring.
 */
export function statusClass(status: number): string {
  if (status >= 500) return 'error';
  if (status >= 400) return 'warning';
  if (status >= 300) return 'info';
  if (status >= 200) return 'success';

  return 'muted';
}
