import app from 'flarum/common/app';
import type { DebugbarPayload, ObservedRequest, Profile } from '../types';

const STORAGE_KEY = 'datlechin-debugbar';

/**
 * How many requests to keep in the picker. The backend prunes its own history
 * independently; this only bounds what one page's bar remembers.
 */
const MAX_REQUESTS = 50;

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

  requests: ObservedRequest[] = [];

  selected: string | null = null;

  protected profiles = new Map<string, Profile>();

  protected loading = new Set<string>();

  protected failed = new Map<string, string>();

  constructor(payload: DebugbarPayload) {
    const persisted = this.read();

    this.open = persisted?.open ?? payload.openByDefault;
    this.panel = persisted?.panel ?? null;
    this.height = persisted?.height ?? 320;

    this.observe({
      id: payload.requestId,
      method: 'GET',
      uri: window.location.pathname + window.location.search,
      status: 200,
      duration: 0,
      time: Date.now() / 1000,
      document: true,
    });
  }

  /**
   * Record a request the page made, and select it.
   *
   * The newest request is always the one the bar shows: when you click
   * something and want to know what it cost, that is the request you meant.
   */
  observe(request: ObservedRequest): void {
    this.requests.unshift(request);
    this.requests = this.requests.slice(0, MAX_REQUESTS);

    this.selected = request.id;
  }

  select(id: string): void {
    this.selected = id;
    this.load(id);
  }

  current(): ObservedRequest | null {
    return this.requests.find((request) => request.id === this.selected) ?? null;
  }

  profile(): Profile | null {
    return this.selected ? this.profiles.get(this.selected) ?? null : null;
  }

  isLoading(): boolean {
    return this.selected !== null && this.loading.has(this.selected);
  }

  error(): string | null {
    return this.selected ? this.failed.get(this.selected) ?? null : null;
  }

  /**
   * Fetch a profile, once.
   *
   * A failure is remembered as well as a success, so a pruned profile does not
   * turn into a request on every redraw.
   */
  load(id: string): void {
    if (this.profiles.has(id) || this.loading.has(id) || this.failed.has(id)) return;

    this.loading.add(id);

    app
      .request<{ data: Profile }>({
        method: 'GET',
        url: `${app.forum.attribute('apiUrl')}/debugbar/profiles/${id}`,
        // The bar must never put an error alert in front of the forum it is
        // supposed to be helping someone debug.
        errorHandler: () => {},
      })
      .then(
        (response) => {
          this.profiles.set(id, response.data);
          this.settle(id);
        },
        (error) => {
          this.failed.set(id, error?.status === 404 ? 'expired' : 'failed');
          this.settle(id);
        }
      );
  }

  toggle(open = !this.open): void {
    this.open = open;
    this.persist();
  }

  show(panel: string): void {
    this.panel = panel;
    this.persist();
  }

  resize(height: number): void {
    this.height = Math.max(160, Math.min(height, Math.round(window.innerHeight * 0.9)));
    this.persist();
  }

  protected settle(id: string): void {
    this.loading.delete(id);
    m.redraw();
  }

  /**
   * The bar's shape outlives the page it was arranged on: navigating in a
   * forum is a full page load, and a bar that collapsed itself on every
   * navigation would be unusable for following a sequence of requests.
   *
   * Storage access is guarded because it throws outright — rather than
   * returning null — in a browser configured to block site data.
   */
  protected persist(): void {
    try {
      const state: PersistedState = { open: this.open, panel: this.panel, height: this.height };

      localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
    } catch {
      // A bar that cannot remember its position is still a usable bar.
    }
  }

  protected read(): PersistedState | null {
    try {
      const raw = localStorage.getItem(STORAGE_KEY);

      return raw ? (JSON.parse(raw) as PersistedState) : null;
    } catch {
      return null;
    }
  }
}
