import type Mithril from 'mithril';
/**
 * The extension id. Locale keys and setting keys are both namespaced with it,
 * and both are built here so that a rename is a one-line change.
 */
export declare const EXTENSION = "datlechin-debugbar";
/**
 * Setting keys, mirroring `Datlechin\FlarumDebugbar\Support\Settings`.
 */
export declare const SETTINGS: {
    readonly maxProfiles: "datlechin-debugbar.max_profiles";
    readonly disabledCollectors: "datlechin-debugbar.disabled_collectors";
    readonly traceQueries: "datlechin-debugbar.trace_queries";
    readonly openByDefault: "datlechin-debugbar.open_by_default";
};
/**
 * A translation shared by both frontends.
 *
 * Flarum only delivers a key to a frontend when its namespace segment matches
 * that frontend, or `lib` — so everything the bar itself says lives under
 * `lib`, because the bar itself runs in both.
 */
export declare function trans(key: string, parameters?: Record<string, unknown>): Mithril.Children;
/**
 * A translation used only by the admin settings page.
 */
export declare function transAdmin(key: string, parameters?: Record<string, unknown>): Mithril.Children;
/**
 * A translation that may not exist — used for panel titles, where a collector
 * added by another extension will have no key of ours to fall back on.
 */
export declare function transIfExists(key: string): Mithril.Children | null;
