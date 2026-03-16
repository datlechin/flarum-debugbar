# PHP Debugbar for Flarum

![License](https://img.shields.io/badge/license-MIT-blue.svg) [![Latest Stable Version](https://img.shields.io/packagist/v/datlechin/flarum-debugbar.svg)](https://packagist.org/packages/datlechin/flarum-debugbar)

Integrates [PHP Debugbar](https://github.com/php-debugbar/php-debugbar) v3.5 into Flarum 2.x, providing a powerful in-browser debug toolbar for development. Inspect SQL queries, events, routes, authentication, cache operations, mail, and more — all without leaving your browser.

> **Warning**: This is a development tool. Never enable debug mode on production sites.

## Installation

```sh
composer require datlechin/flarum-debugbar:"*"
```

Enable the extension in the admin panel, then ensure `debug` is set to `true` in your `config.php`:

```php
'debug' => true,
```

Publish debugbar assets (usually automatic, but can be done manually):

```sh
php flarum debugbar:publish
```

## Features

### Debugbar Tabs

| Tab        | Description                                                               |
| ---------- | ------------------------------------------------------------------------- |
| Messages   | Log messages from the request lifecycle                                   |
| Timeline   | Visual breakdown of request timing (forum, handler, API calls)            |
| Exceptions | PHP exceptions and errors                                                 |
| Flarum     | Framework version, PHP version, database/queue/cache/mail/session drivers |
| Queries    | All SQL queries with bindings and execution time                          |
| Route      | Matched route name, handler, HTTP method, parameters                      |
| Auth       | Current user, groups, authentication method, session info                 |
| API        | JSON:API resource, endpoint, includes, filters, pagination                |
| Settings   | All Flarum settings grouped by extension (sensitive values masked)        |
| Events     | All events fired during the request                                       |
| Cache      | Cache hits, misses, writes, and deletes                                   |
| Mail       | Emails sent/queued during the request                                     |
| Extensions | All enabled extensions with versions                                      |

### Additional Features

- **AJAX Tracking** — Flarum SPA navigation (API calls via `fetch`) are captured and displayed in a dedicated AJAX tab
- **Request History** — Browse past requests via the OpenHandler (clock icon in the toolbar)
- **Dark Theme** — Automatically matches your browser's light/dark preference
- **Auto-hide Empty Tabs** — Tabs with no data (e.g., Cache 0, Mail 0) are hidden
- **Storage Auto-prune** — Old request data is automatically cleaned up after 24 hours

### Console Commands

```sh
php flarum debugbar:publish   # Publish/repair debugbar assets symlink
php flarum debugbar:clear     # Clear stored request history
```

## For Extension Developers

Use `DebugbarHelper` to log messages and measure performance from your extension code:

```php
use Datlechin\FlarumDebugbar\DebugbarHelper;

// Log messages
DebugbarHelper::info('Loading custom data');
DebugbarHelper::warning('Deprecated method called');
DebugbarHelper::error('Something went wrong');
DebugbarHelper::debug('Variable value: ' . $value);

// Measure execution time
DebugbarHelper::startMeasure('my-operation', 'My Heavy Operation');
// ... do work ...
DebugbarHelper::stopMeasure('my-operation');

// Add exceptions
DebugbarHelper::addException($e);
```

All methods are safe to call even when the debugbar is disabled — they silently no-op.

## How It Works

The extension registers PSR-15 middleware on the `forum`, `admin`, and `api` stacks:

- **Forum/Admin**: Injects the debugbar HTML toolbar into page responses
- **API**: Sends debug data as HTTP response headers for AJAX tracking

All functionality is wrapped in `Extend\Conditional` and only activates when `debug => true` in `config.php`. When debug mode is off, the extension has zero overhead.

## Links

- [PHP Debugbar](https://github.com/php-debugbar/php-debugbar)
- [Flarum](https://flarum.org)
- [Packagist](https://packagist.org/packages/datlechin/flarum-debugbar)
