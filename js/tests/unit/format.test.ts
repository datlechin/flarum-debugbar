import { bytes, count, duration, percentage, statusClass } from '../../src/common/utils/format';

describe('duration', () => {
  it.each([
    [2.5, '2.5 s'],
    [1, '1 s'],
    [0.368, '368 ms'],
    [0.0125, '12.5 ms'],
    [0.001, '1 ms'],
    [0.00025, '250 µs'],
    [0, '0 µs'],
  ])('%s seconds reads as %s', (seconds, expected) => {
    expect(duration(seconds)).toBe(expected);
  });

  it('shows three significant figures, whatever the magnitude', () => {
    // A list of durations is read by scanning it, so every figure has to be
    // comparable at a glance rather than varying in length.
    expect(duration(0.123456)).toBe('123 ms');
    expect(duration(0.0123456)).toBe('12.3 ms');
    expect(duration(0.00123456)).toBe('1.23 ms');
  });

  it('does not leave a trailing zero on a whole number', () => {
    expect(duration(0.002)).toBe('2 ms');
    expect(duration(0.05)).toBe('50 ms');
  });

  it('says nothing rather than something wrong for a value that is not a duration', () => {
    expect(duration(NaN)).toBe('—');
    expect(duration(-1)).toBe('—');
    expect(duration(Infinity)).toBe('—');
  });
});

describe('bytes', () => {
  it.each([
    [0, '0 B'],
    [512, '512 B'],
    [1024, '1 KiB'],
    [1536, '1.5 KiB'],
    [2097152, '2 MiB'],
    [128450560, '123 MiB'],
    [1073741824, '1 GiB'],
  ])('%s bytes reads as %s', (value, expected) => {
    expect(bytes(value)).toBe(expected);
  });

  it('uses binary units, because that is what PHP reports', () => {
    expect(bytes(1000)).toBe('1000 B');
    expect(bytes(1024)).toBe('1 KiB');
  });

  it('stops at the largest unit it knows', () => {
    expect(bytes(1024 ** 5)).toBe('1024 TiB');
  });

  it('says nothing rather than something wrong for a value that is not a size', () => {
    expect(bytes(NaN)).toBe('—');
    expect(bytes(-1)).toBe('—');
  });
});

describe('percentage', () => {
  it('rounds to a whole number', () => {
    expect(percentage(2 / 3)).toBe('67%');
    expect(percentage(0)).toBe('0%');
    expect(percentage(1)).toBe('100%');
  });
});

describe('count', () => {
  it('separates thousands so a four-figure count is recognisable as one', () => {
    expect(count(1000)).toBe((1000).toLocaleString());
    expect(count(7)).toBe('7');
  });
});

describe('statusClass', () => {
  it.each([
    [200, 'success'],
    [201, 'success'],
    [204, 'success'],
    [302, 'info'],
    [304, 'info'],
    [404, 'warning'],
    [422, 'warning'],
    [500, 'error'],
    [503, 'error'],
    [0, 'muted'],
  ])('%s is %s', (status, expected) => {
    expect(statusClass(status)).toBe(expected);
  });
});
