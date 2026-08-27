import type { DebugbarPayload, ObservedRequest, Profile } from '../types';
interface PersistedState {
    open: boolean;
    panel: string | null;
    height: number;
}
/**
 * Everything the bar knows.
 *
 * Requests arrive here from two directions. The page it is running on
 * announces itself through the document payload, and every subsequent XHR is
 * noticed as it completes (see `observeRequests`). Neither carries the profile
 * itself — only its id — so the data behind a request is fetched the first
 * time someone looks at it, and a page that nobody opens the bar on pays for
 * exactly one extra request.
 */
export default class DebugbarState {
    open: boolean;
    /** The collector name of the visible panel. */
    panel: string | null;
    /** The bar's height in pixels when open. */
    height: number;
    requests: ObservedRequest[];
    selected: string | null;
    protected profiles: Map<string, Profile>;
    protected loading: Set<string>;
    protected failed: Map<string, string>;
    constructor(payload: DebugbarPayload);
    /**
     * Record a request the page made, and select it.
     *
     * The newest request is always the one the bar shows: when you click
     * something and want to know what it cost, that is the request you meant.
     */
    observe(request: ObservedRequest): void;
    select(id: string): void;
    current(): ObservedRequest | null;
    profile(): Profile | null;
    isLoading(): boolean;
    error(): string | null;
    /**
     * Fetch a profile, once.
     *
     * A failure is remembered as well as a success, so a pruned profile does not
     * turn into a request on every redraw.
     */
    load(id: string): void;
    toggle(open?: boolean): void;
    show(panel: string): void;
    resize(height: number): void;
    protected settle(id: string): void;
    /**
     * The bar's shape outlives the page it was arranged on: navigating in a
     * forum is a full page load, and a bar that collapsed itself on every
     * navigation would be unusable for following a sequence of requests.
     *
     * Storage access is guarded because it throws outright — rather than
     * returning null — in a browser configured to block site data.
     */
    protected persist(): void;
    protected read(): PersistedState | null;
}
export {};
