# Changelog

## 2.0.0 - 2026-08-27

Rewritten. The bar is now a Flarum frontend rather than a third-party toolbar injected into the page, and the `php-debugbar/php-debugbar` dependency is gone.

### Why

The old implementation rendered PHP Debugbar's own interface, which meant three things it could not avoid:

- its assets had to reach the browser, which was done by symlinking `vendor/php-debugbar/php-debugbar/resources` into `public/assets/debugbar` at boot, with a `debugbar:publish` command to repair the symlink when that failed;
- its markup had to reach the page, which was done by string-replacing `</head>` and `</body>` in the response body;
- it had to look like Flarum, which was done with ~100 lines of CSS overriding its custom properties — including a hardcoded `#e7742e`, so the bar was orange on a forum whose primary colour was not.

Rendering the data in Flarum's own frontend removes all three by construction.

### Added

- A Flarum-native bar, built from core's components and design tokens, so it follows the forum's primary colour, colour scheme, dark mode and high-contrast modes. Each part is a pattern core already uses:

  | | Modelled on |
  | --- | --- |
  | The card | `.Composer` — Flarum's other bottom-docked panel: a card on the page's own `.container`, rounded at the top, `--control-bg` when collapsed and `--body-bg` when open |
  | The tab strip | `.Tabs-nav` as `SearchModal` uses it — text buttons, no icons, the active one underlined in the primary colour |
  | List rows | `.Dropdown-menu > li` — padding and hover, and no rule between rows. In Flarum a line separates *groups* |
  | Group headings | `.Dropdown-header` |
  | Panel headers | `.HeaderList-header`, the strip above the notifications list |
  | Headline figures | `LabelValue` |
  | The request picker | `Dropdown` with `DetailedDropdownItem` rows and `Dropdown-menu--top` |
- An admin settings page: retention, query origin tracing, whether the bar starts expanded, and a switch per panel.
- A request picker. Every request the page makes is listed — the page load and every XHR — and any of them can be inspected. Requests the frontend never saw, such as a form post that redirected, are available too.
- Query origins: the line of application code that ran each statement.
- Query parameters, and the statement with those parameters substituted in. The previous version advertised bindings but discarded them (`'params' => new stdClass()`).
- A timeline that accounts for the whole request — boot, middleware, route handler, and each internal API dispatch — rather than only the parts that were explicitly measured.
- `Extend\Debugbar`, an extender for registering collectors from another extension, and a `panels` extension point on the frontend for rendering them.
- Redaction of anything whose key looks like a credential, before it is written to disk.
- `debugbar:clear`, and an admin button that does the same.
- Locale files. The bar had no translatable strings at all.
- Tests: 174 unit and 37 integration, covering what unit tests structurally cannot — that the middleware lands in all three stacks and in the right order, that the API endpoints exist and are administrator-only, that the frontend payload is written for the right people, that `Extend\Debugbar` registers a collector end to end, and that a page load produces one profile rather than one per internal API dispatch.

### Changed

- Profiles are stored as JSON under `storage/debugbar`, keyed by a random id returned in an `X-Debugbar-Id` header, and read back over an admin-only API endpoint. Nothing is injected into the response body.
- The bar is visible to administrators only. It was previously visible to everyone on a forum in debug mode.
- Cache tracing decorates the cache *store* rather than the repository, so `increment()`, `decrement()`, `forever()` and `touch()` are seen too. The decorator keeps the store's `LockProvider` interface, which core checks before choosing an atomic path.
- Eloquent's model events are counted rather than listed individually.
- Settings are grouped by the extension that owns them.
- `DebugbarHelper`'s static state is replaced by a container-bound `Debugbar` service, which is a no-op outside debug mode so callers still need no guard.

### Fixed

- `Repository::has()` is implemented in terms of `get()`, so the old repository-level cache decorator counted every existence check twice.
- Mail was paired by position ("the most recent entry still marked as sending"), which attributed the wrong `sent` event as soon as two messages were in flight — the normal shape of a notification fan-out. Messages are now matched by object identity.
- Settings whose key merely *contained* `key` were redacted, including every setting belonging to `datlechin-keyboard-shortcuts`. Matching is now on whole words.
- Values that could not survive `json_encode` — invalid UTF-8 from a BLOB column, for instance — took the whole profile with them.
- The Messages panel was empty for exactly the request anyone would open it to look at. Flarum's error handler sits *below* this extension's outermost middleware, so a failure arrived there already caught and formatted into a 500, and the exception was gone. It now registers as an error reporter, which is how Flarum offers this.
- The Events panel repeated every event's name as its own payload — Laravel dispatches a class-based event with the event object as its only payload, so the type *is* the name. Database events are now counted rather than listed too: two fire per query, and the Queries panel is the detailed view of them.
- Settings were grouped on "anything before a dot", which invented an `assets_dirty` extension to put core's `assets_dirty.admin` under. The prefix is now matched against the extensions that are actually installed.
- Exception file paths and stack frames were absolute; query origins were already relative. Both now shorten to the part that identifies them.
- The request picker opened downward, off the bottom of the screen, since the bar is docked to the bottom edge. It now uses core's `Dropdown-menu--top`.
- Both `@phpstan-ignore` annotations are gone: the middleware is positioned by class name rather than by container binding name.
- Around a dozen `catch (\Throwable) {}` blocks that discarded the reason for a failure now either report through Flarum's logger or do not catch at all.

### Removed

- The `php-debugbar/php-debugbar` dependency.
- `debugbar:publish`, and the symlink into `public/assets` that it existed to repair. An existing symlink is left behind by the upgrade; nothing reads it, and `rm public/assets/debugbar` is safe.
- `DebugbarHelper`, replaced by the container-bound `Debugbar` service. The methods have the same names.

## 1.0.x

Integration of PHP Debugbar 3.5 into Flarum 2.x.
