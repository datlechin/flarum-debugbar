import app from 'flarum/common/app';
import { extend } from 'flarum/common/extend';
import type Mithril from 'mithril';

import Debugbar from './components/Debugbar';
import DebugbarState from './states/DebugbarState';
import type { DebugbarPayload } from './types';

/**
 * The response header that names a request's profile.
 * Mirrors `Http\Middleware\CollectProfile::HEADER`.
 */
const HEADER = 'X-Debugbar-Id';

declare module 'flarum/common/Application' {
  export default interface Application {
    debugbar?: DebugbarState;
  }
}

/**
 * Start the debug bar, if this page has one.
 *
 * Everything here hangs off the presence of `app.data.debugbar`, which the
 * backend only writes in debug mode and only for administrators. On any other
 * page these extenders run, find nothing, and cost nothing.
 */
export default function boot(): void {
  mountBar();
  observeRequests();
}

/**
 * Attach the bar once the app has finished mounting.
 *
 * `Application#mount` is extended rather than doing this from the initializer
 * because initializers run before the app has a DOM to sit alongside — and
 * because it covers the forum and the admin panel with one hook, neither of
 * which overrides `mount`.
 */
function mountBar(): void {
  extend<{ mount(basePath?: string): void }, 'mount'>('flarum/common/Application', 'mount', function () {
    const payload = app.data.debugbar as DebugbarPayload | undefined;

    if (!payload || app.debugbar) return;

    const state = new DebugbarState(payload);

    app.debugbar = state;

    const element = document.createElement('div');
    element.className = 'DebugbarRoot';
    document.body.appendChild(element);

    m.mount(element, { view: (): Mithril.Children => <Debugbar state={state} /> });

    // Fetched whether or not the bar is open. A collapsed bar still reports
    // this page's status, time, memory and query count — which is the whole
    // reason to leave it collapsed rather than closed — and those figures
    // only exist in the profile.
    state.load(payload.requestId);
  });
}

/**
 * Notice every request the page makes.
 *
 * `transformRequestOptions` is where core assembles the options it hands to
 * Mithril, including the `extract` function that reads the raw `XMLHttpRequest`
 * — the only place with access to the response headers. Wrapping it means the
 * bar sees every request the forum makes, without touching `fetch` or
 * `XMLHttpRequest` themselves, and without a second code path for requests
 * that failed.
 */
function observeRequests(): void {
  extend<any, any>('flarum/common/Application', 'transformRequestOptions', function (options: any, original: any) {
    if (!options || typeof options.extract !== 'function') return;

    const startedAt = performance.now();
    const extract = options.extract;

    options.extract = (xhr: XMLHttpRequest) => {
      record(xhr, original, (performance.now() - startedAt) / 1000);

      // `extract` throws on any non-2xx status, which is how core turns a
      // failed response into a rejected promise. The profile is recorded
      // first, so a request that failed is still one you can look at.
      return extract(xhr);
    };
  });
}

function record(xhr: XMLHttpRequest, options: any, duration: number): void {
  const state = app.debugbar;

  // Requests that were not profiled carry no header — which includes the
  // bar's own calls to read profiles back, so it never lists itself.
  const id = state && typeof xhr?.getResponseHeader === 'function' ? xhr.getResponseHeader(HEADER) : null;

  if (!state || !id) return;

  state.observe({
    id,
    method: String(options?.method ?? 'GET').toUpperCase(),
    uri: relative(String(options?.url ?? '')),
    status: xhr.status,
    duration,
    time: Date.now() / 1000,
    document: false,
  });

  // The newest request is the one the bar shows, so its profile is fetched
  // straight away — otherwise the summary would go blank every time the page
  // made a request. The fetch is not itself profiled, so this cannot cascade.
  state.load(id);

  m.redraw();
}

/**
 * Every request the forum makes goes to the same origin, and most to the same
 * API root, so neither is worth the width in a list of them.
 */
function relative(url: string): string {
  const apiUrl = String(app.forum.attribute('apiUrl') ?? '');

  if (apiUrl && url.startsWith(apiUrl)) return url.slice(apiUrl.length) || '/';

  try {
    return new URL(url, window.location.origin).pathname;
  } catch {
    return url;
  }
}
