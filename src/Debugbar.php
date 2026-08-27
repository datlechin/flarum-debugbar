<?php

/*
 * This file is part of datlechin/flarum-debugbar.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\FlarumDebugbar;

use Datlechin\FlarumDebugbar\Collector\CollectorInterface;
use Datlechin\FlarumDebugbar\Collector\MessageCollector;
use Datlechin\FlarumDebugbar\Collector\TimelineCollector;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The debug bar for the request currently being served.
 *
 * There is one of these per request — Flarum serves a single request per
 * process — and it is a normal container singleton rather than a global, so
 * anything that wants it asks for it:
 *
 * ```php
 * public function __construct(protected Debugbar $debugbar) {}
 * ```
 *
 * or, from somewhere without constructor injection, `resolve(Debugbar::class)`.
 *
 * It is bound whether or not the forum is in debug mode. Outside debug mode it
 * simply has no collectors, and every method here is a cheap no-op — so code
 * that logs to the debug bar does not have to guard the call.
 */
class Debugbar
{
    /**
     * @var array<string, CollectorInterface>
     */
    protected array $collectors = [];

    protected string $id;

    protected float $startedAt;

    /**
     * Set when the request being served is one whose whole purpose is to read
     * profiles back out. Profiling it would add a profile every time the bar
     * refreshed, which is both noise and an unbounded loop.
     */
    protected bool $discarded = false;

    public function __construct(
        protected bool $enabled = true,
        ?float $startedAt = null,
    ) {
        $this->id = bin2hex(random_bytes(8));
        $this->startedAt = $startedAt ?? self::requestStartTime();
    }

    /**
     * When this request began, as far as PHP can tell.
     *
     * `REQUEST_TIME_FLOAT` is stamped by the SAPI before PHP runs a single
     * line of Flarum, so measuring from it includes autoloading, container
     * boot and extender application — exactly the part of a slow request
     * people are least able to see otherwise.
     */
    public static function requestStartTime(): float
    {
        return (float) ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true));
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * The id the profile for this request will be stored under. Known up front
     * so the frontend can be told where to look before the request has
     * finished.
     */
    public function id(): string
    {
        return $this->id;
    }

    public function startedAt(): float
    {
        return $this->startedAt;
    }

    public function addCollector(CollectorInterface $collector): void
    {
        $this->collectors[$collector->name()] = $collector;
    }

    /**
     * @return array<string, CollectorInterface>
     */
    public function collectors(): array
    {
        return $this->collectors;
    }

    /**
     * @template T of CollectorInterface
     * @param class-string<T>|string $name A collector name, or its class name.
     * @return ($name is class-string<T> ? T|null : CollectorInterface|null)
     */
    public function collector(string $name): ?CollectorInterface
    {
        if (isset($this->collectors[$name])) {
            return $this->collectors[$name];
        }

        foreach ($this->collectors as $collector) {
            if ($collector instanceof $name) {
                return $collector;
            }
        }

        return null;
    }

    /**
     * Drop this request's profile instead of storing it.
     */
    public function discard(): void
    {
        $this->discarded = true;
    }

    public function isDiscarded(): bool
    {
        return $this->discarded;
    }

    /**
     * Everything the collectors saw, ready to be stored.
     *
     * Returns null when there is nothing worth storing: the bar is off, or
     * this request asked to be left out of the history.
     */
    public function collect(ServerRequestInterface $request, ResponseInterface $response): ?Profile
    {
        if (! $this->enabled || $this->discarded) {
            return null;
        }

        $data = [];

        foreach ($this->collectors as $name => $collector) {
            $data[$name] = $collector->collect($request, $response);
        }

        return new Profile(
            id: $this->id,
            time: $this->startedAt,
            method: $request->getMethod(),
            uri: $this->relativeUri($request),
            status: $response->getStatusCode(),
            duration: microtime(true) - $this->startedAt,
            memory: memory_get_peak_usage(true),
            data: $data,
        );
    }

    // ------------------------------------------------------------------
    // Conveniences for extension code
    // ------------------------------------------------------------------

    public function log(string $message, string $level = MessageCollector::INFO): void
    {
        $this->messages()?->add($message, $level);
    }

    public function info(string $message): void
    {
        $this->log($message, MessageCollector::INFO);
    }

    public function warning(string $message): void
    {
        $this->log($message, MessageCollector::WARNING);
    }

    public function error(string $message): void
    {
        $this->log($message, MessageCollector::ERROR);
    }

    public function debug(string $message): void
    {
        $this->log($message, MessageCollector::DEBUG);
    }

    public function exception(\Throwable $exception): void
    {
        $this->messages()?->addException($exception);
    }

    /**
     * Time a piece of work and show it on the timeline.
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public function measure(string $label, callable $callback): mixed
    {
        $timeline = $this->timeline();

        if (! $timeline) {
            return $callback();
        }

        return $timeline->measure($label, $callback);
    }

    public function startMeasure(string $name, ?string $label = null): void
    {
        $this->timeline()?->start($name, $label);
    }

    public function stopMeasure(string $name): void
    {
        $this->timeline()?->stop($name);
    }

    /**
     * Add a span for work that has already finished.
     */
    public function recordMeasure(string $name, string $label, float $start, float $end): void
    {
        $this->timeline()?->record($name, $label, $start, $end);
    }

    protected function messages(): ?MessageCollector
    {
        $collector = $this->collector(MessageCollector::class);

        return $collector instanceof MessageCollector ? $collector : null;
    }

    protected function timeline(): ?TimelineCollector
    {
        $collector = $this->collector(TimelineCollector::class);

        return $collector instanceof TimelineCollector ? $collector : null;
    }

    /**
     * The request path and query, without the scheme and host.
     *
     * Profiles are listed one per line in a bar a few hundred pixels wide, and
     * every entry shares the same origin, so the origin is all noise.
     */
    protected function relativeUri(ServerRequestInterface $request): string
    {
        $uri = $request->getUri();
        $query = $uri->getQuery();

        return ($uri->getPath() ?: '/').($query === '' ? '' : '?'.$query);
    }
}
