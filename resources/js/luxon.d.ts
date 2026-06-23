// Ambient shim for luxon, which ships plain JS source with no bundled
// declaration files. Declaring the module here silences the implicit-any error
// from TypeScript when bundler module resolution resolves luxon to its JS source.
declare module 'luxon' {
    export class DateTime {
        static fromISO(iso: string, opts?: unknown): DateTime
        static now(): DateTime
        static fromMillis(ms: number, opts?: unknown): DateTime
        static fromSeconds(seconds: number, opts?: unknown): DateTime
        static fromJSDate(date: Date, opts?: unknown): DateTime
        static DATETIME_MED: unknown
        static DATETIME_SHORT: unknown
        static DATE_MED: unknown
        static DATE_SHORT: unknown
        static TIME_SIMPLE: unknown
        toLocaleString(format?: unknown): string
        toISO(): string | null
        toMillis(): number
        toJSDate(): Date
        plus(duration: unknown): DateTime
        minus(duration: unknown): DateTime
        diff(other: DateTime, units?: unknown): Duration
        isValid: boolean
        invalidReason: string | null
        [key: string]: unknown
    }
    export class Duration {
        as(unit: string): number
        toISO(): string
        toMillis(): number
        [key: string]: unknown
    }
    export class Interval {
        [key: string]: unknown
    }
    export class Info {
        [key: string]: unknown
    }
}
