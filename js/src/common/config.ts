import app from 'flarum/common/app';
import type Mithril from 'mithril';

/**
 * The extension id. Locale keys and setting keys are both namespaced with it,
 * and both are built here so that a rename is a one-line change.
 */
export const EXTENSION = 'datlechin-debugbar';

/**
 * Setting keys, mirroring `Datlechin\FlarumDebugbar\Support\Settings`.
 */
export const SETTINGS = {
  maxProfiles: `${EXTENSION}.max_profiles`,
  disabledCollectors: `${EXTENSION}.disabled_collectors`,
  traceQueries: `${EXTENSION}.trace_queries`,
  openByDefault: `${EXTENSION}.open_by_default`,
} as const;

/**
 * A translation shared by both frontends.
 *
 * Flarum only delivers a key to a frontend when its namespace segment matches
 * that frontend, or `lib` — so everything the bar itself says lives under
 * `lib`, because the bar itself runs in both.
 */
export function trans(key: string, parameters: Record<string, unknown> = {}): Mithril.Children {
  return app.translator.trans(`${EXTENSION}.lib.${key}`, parameters);
}

/**
 * A translation used only by the admin settings page.
 */
export function transAdmin(key: string, parameters: Record<string, unknown> = {}): Mithril.Children {
  return app.translator.trans(`${EXTENSION}.admin.${key}`, parameters);
}

/**
 * A translation that may not exist — used for panel titles, where a collector
 * added by another extension will have no key of ours to fall back on.
 */
export function transIfExists(key: string): Mithril.Children | null {
  const full = `${EXTENSION}.lib.${key}`;

  return app.translator.translations[full] ? app.translator.trans(full) : null;
}
