import { APP_TIME_ZONE, formatDate, formatDateTime } from './collectorData';

describe('collector date formatting', () => {
  it('uses Buenos Aires when UTC is already on the next day', () => {
    const instant = new Date('2026-10-15T01:30:00.000Z');

    expect(APP_TIME_ZONE).toBe('America/Argentina/Buenos_Aires');
    expect(formatDate(instant)).toContain('14 de octubre');
    expect(formatDateTime(instant)).toContain('14/10/2026');
    expect(formatDateTime(instant)).toContain('22:30');
  });
});
