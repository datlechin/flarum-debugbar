/**
 * The shapes the backend collectors produce.
 *
 * Each interface here mirrors exactly one PHP collector's `collect()` return
 * value; keeping the two in step is what lets the panels be written against
 * real types instead of `any`.
 */
export interface ProfileSummary {
    id: string;
    time: number;
    method: string;
    uri: string;
    status: number;
    duration: number;
    memory: number;
}
export interface Profile extends ProfileSummary {
    data: Record<string, unknown>;
}
export interface Measure {
    name: string;
    label: string;
    /** Seconds after the request started. */
    start: number;
    duration: number;
    unfinished: boolean;
}
export interface TimelineData {
    start: number;
    duration: number;
    measures: Measure[];
}
export interface Query {
    sql: string;
    preview: string;
    bindings: string[];
    duration: number;
    connection: string;
    origin: string | null;
    occurrences: number;
}
export interface QueriesData {
    count: number;
    dropped: number;
    duplicates: number;
    duration: number;
    queries: Query[];
}
export type MessageLevel = 'debug' | 'info' | 'warning' | 'error';
export interface Message {
    time: number;
    level: MessageLevel;
    message: string;
    file?: string;
    line?: number;
    trace?: string[];
}
export interface MessagesData {
    count: number;
    messages: Message[];
}
export interface RequestRoute {
    name: string | null;
    handler: string;
    parameters: Record<string, string>;
    internal: boolean;
}
export interface RequestActor {
    id?: number | null;
    username?: string | null;
    isGuest?: boolean;
    isAdmin?: boolean;
    groups?: string[];
    authentication: string;
}
export interface RequestData {
    method: string;
    uri: string;
    status: number;
    route: RequestRoute;
    actor: RequestActor;
    query: Record<string, string>;
    jsonApi: Record<string, string>;
    requestHeaders: Record<string, string>;
    responseHeaders: Record<string, string>;
}
export interface EventEntry {
    name: string;
    time: number;
    payload: string[];
}
export interface EventsData {
    count: number;
    dropped: number;
    events: EventEntry[];
    collapsed: Array<{
        name: string;
        count: number;
    }>;
}
export type CacheOperationType = 'hit' | 'miss' | 'write' | 'forget' | 'flush';
export interface CacheOperation {
    type: CacheOperationType;
    key: string;
    time: number;
}
export interface CacheData {
    count: number;
    dropped: number;
    totals: Record<CacheOperationType, number>;
    hitRate: number | null;
    operations: CacheOperation[];
}
export interface MailMessage {
    status: 'sending' | 'sent';
    time: number;
    subject: string;
    from: string[];
    to: string[];
    cc: string[];
    bcc: string[];
    replyTo: string[];
    body: string;
}
export interface MailData {
    count: number;
    dropped: number;
    messages: MailMessage[];
}
export interface SettingEntry {
    /** The full key, as stored. */
    key: string;
    /** The key without its group prefix, which the heading already states. */
    name: string;
    value: string;
    sensitive: boolean;
}
export interface SettingsData {
    count: number;
    groups: Array<{
        name: string;
        settings: SettingEntry[];
    }>;
}
export interface ExtensionEntry {
    id: string;
    title: string;
    version: string | null;
    enabled: boolean;
    dependencies: string[];
}
export interface ExtensionsData {
    count: number;
    enabled: number;
    extensions: ExtensionEntry[];
}
export interface EnvironmentData {
    groups: Array<{
        name: string;
        values: Record<string, string>;
    }>;
}
/**
 * A request the page made and the debug bar noticed, before (or without) its
 * profile having been fetched.
 */
export interface ObservedRequest {
    id: string;
    method: string;
    uri: string;
    status: number;
    /** Round-trip time as the browser measured it, in seconds. */
    duration: number;
    /**
     * When the browser saw it, as a unix timestamp in seconds. Three calls to
     * the same endpoint are otherwise indistinguishable in the picker.
     */
    time: number;
    /** The page load itself, rather than an XHR it went on to make. */
    document: boolean;
}
/**
 * What `Frontend\AddDebugbar` puts in the page payload.
 */
export interface DebugbarPayload {
    requestId: string;
    openByDefault: boolean;
}
