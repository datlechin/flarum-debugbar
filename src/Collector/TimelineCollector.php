<?php

/*
 * This file is part of datlechin/flarum-debugbar.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\FlarumDebugbar\Collector;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Named spans of the request, drawn as bars against the request's own duration.
 *
 * Measures may nest — the frontend derives nesting from the spans themselves
 * rather than from anything recorded here, so a caller never has to declare it.
 */
class TimelineCollector implements CollectorInterface
{
    /**
     * @var array<string, array{label: string, start: float, end: float|null}>
     */
    protected array $measures = [];

    protected int $sequence = 0;

    public function __construct(
        protected float $startedAt,
    ) {
    }

    public function name(): string
    {
        return 'timeline';
    }

    public function start(string $name, ?string $label = null): void
    {
        // Reusing a name would overwrite the earlier span, so the later one
        // gets a name of its own. This applies whether the first is still
        // running (a nested measure) or already closed (the same operation
        // timed twice in one request) — the closed case is the easy one to
        // miss, and it silently loses the first of the two.
        if (isset($this->measures[$name])) {
            $name .= '#'.(++$this->sequence);
        }

        $this->measures[$name] = [
            'label' => $label ?? $name,
            'start' => microtime(true),
            'end' => null,
        ];
    }

    public function stop(string $name): void
    {
        // The most recent unfinished span with this name, so that a repeated
        // name closes in the order it was opened.
        foreach (array_reverse(array_keys($this->measures)) as $key) {
            if (($key === $name || str_starts_with($key, $name.'#')) && $this->measures[$key]['end'] === null) {
                $this->measures[$key]['end'] = microtime(true);

                return;
            }
        }
    }

    /**
     * Record a span that has already happened.
     *
     * For work that finished before anything was in a position to time it —
     * chiefly Flarum's own bootstrap, which is over by the time the first
     * middleware runs, and is very often the largest share of a slow request.
     */
    public function record(string $name, string $label, float $start, float $end): void
    {
        $this->measures[$name] = ['label' => $label, 'start' => $start, 'end' => $end];
    }

    /**
     * Time a callable, closing the span even if it throws.
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public function measure(string $label, callable $callback): mixed
    {
        $name = $label.'#'.(++$this->sequence);

        $this->measures[$name] = [
            'label' => $label,
            'start' => microtime(true),
            'end' => null,
        ];

        try {
            return $callback();
        } finally {
            $this->measures[$name]['end'] = microtime(true);
        }
    }

    public function collect(ServerRequestInterface $request, ResponseInterface $response): array
    {
        $now = microtime(true);
        $measures = [];

        foreach ($this->measures as $name => $measure) {
            // A span left open means the code that opened it threw, or simply
            // never closed it. Ending it here is more useful than dropping it:
            // where it started is often the point of interest.
            $end = $measure['end'] ?? $now;

            $measures[] = [
                'name' => $name,
                'label' => $measure['label'],
                'start' => $measure['start'] - $this->startedAt,
                'duration' => $end - $measure['start'],
                'unfinished' => $measure['end'] === null,
            ];
        }

        usort($measures, fn (array $a, array $b) => $a['start'] <=> $b['start']);

        return [
            'start' => $this->startedAt,
            'duration' => $now - $this->startedAt,
            'measures' => $measures,
        ];
    }
}
