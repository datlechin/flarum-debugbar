/**
 * Number formatting for the panels.
 *
 * A debug bar is read by scanning it, so every figure is shown at a precision
 * you can compare at a glance: three significant figures, and a unit chosen so
 * the number in front of it is between 1 and 999.
 */
/**
 * A duration in seconds, as a human-readable string.
 */
export declare function duration(seconds: number): string;
/**
 * A byte count, in binary units, as PHP's `memory_get_peak_usage` reports it.
 */
export declare function bytes(value: number): string;
export declare function percentage(fraction: number): string;
/**
 * A count, with thousands separated so a four-figure query count is
 * recognisable as one at a glance.
 */
export declare function count(value: number): string;
/**
 * A wall-clock time, for the request picker.
 */
export declare function time(unixSeconds: number): string;
/**
 * The band an HTTP status falls into, for colouring.
 */
export declare function statusClass(status: number): string;
