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

use Datlechin\FlarumDebugbar\Support\SourcePath;
use Datlechin\FlarumDebugbar\Support\Values;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Events\QueryExecuted;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Every statement the request sent to the database.
 *
 * As well as the statements themselves this reports the two things people
 * actually open the panel to find: which query was run more than once with the
 * same parameters, and which line of application code ran it.
 */
class QueryCollector implements CollectorInterface, SubscribesToEvents
{
    protected const LIMIT = 1000;

    /**
     * Frames belonging to the database machinery itself. The interesting frame
     * is the first one below all of this — the application code that asked.
     *
     * This extension's own frames are excluded separately, by absolute path:
     * matching them by a fragment of their Composer install path was wrong the
     * moment the package was installed any other way, and reported every query
     * in the forum as coming from this file.
     */
    protected const INTERNAL_FRAMES = [
        '/illuminate/database/',
        '/illuminate/support/',
        '/illuminate/events/',
        '/laravel/framework/',
        '/flarum/core/src/Database/',
    ];

    /**
     * @var list<array<string, mixed>>
     */
    protected array $queries = [];

    protected int $dropped = 0;

    protected float $totalDuration = 0.0;

    public function __construct(
        protected SourcePath $paths,
        protected bool $traceOrigin = true,
    ) {
    }

    public function name(): string
    {
        return 'queries';
    }

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(QueryExecuted::class, $this->record(...));
    }

    public function record(QueryExecuted $event): void
    {
        // `QueryExecuted::$time` is milliseconds; everything else in a profile
        // is seconds, so it is converted once, here, rather than in each of
        // the places that later add it up.
        $duration = $event->time / 1000;

        $this->totalDuration += $duration;

        if (count($this->queries) >= self::LIMIT) {
            $this->dropped++;

            return;
        }

        $bindings = $this->formatBindings($event->bindings);

        $this->queries[] = [
            'sql' => Values::printable($event->sql),
            'preview' => $this->interpolate($event->sql, $bindings),
            'bindings' => $bindings,
            'duration' => $duration,
            'connection' => $event->connectionName,
            'origin' => $this->traceOrigin ? $this->origin() : null,
        ];
    }

    public function collect(ServerRequestInterface $request, ResponseInterface $response): array
    {
        $queries = $this->queries;

        // A query that ran more than once with exactly the same parameters
        // returned exactly the same rows: the repeat was avoidable. Identity
        // is (statement, parameters, connection) — the same statement with
        // different parameters is the ordinary shape of a loop over ids, and
        // flagging it would bury the real duplicates.
        $seen = [];

        foreach ($queries as $query) {
            $fingerprint = $this->fingerprint($query);
            $seen[$fingerprint] = ($seen[$fingerprint] ?? 0) + 1;
        }

        $duplicates = 0;

        foreach ($queries as $index => $query) {
            $occurrences = $seen[$this->fingerprint($query)];

            $queries[$index]['occurrences'] = $occurrences;

            if ($occurrences > 1) {
                $duplicates++;
            }
        }

        return [
            'count' => count($this->queries) + $this->dropped,
            'dropped' => $this->dropped,
            'duplicates' => $duplicates,
            'duration' => $this->totalDuration,
            'queries' => $queries,
        ];
    }

    /**
     * @param array<string, mixed> $query
     */
    protected function fingerprint(array $query): string
    {
        return md5($query['sql'].'|'.implode('|', $query['bindings']).'|'.$query['connection']);
    }

    /**
     * @param array<array-key, mixed> $bindings
     * @return list<string>
     */
    protected function formatBindings(array $bindings): array
    {
        return array_values(array_map(
            fn (mixed $binding) => Values::stringify($binding, 120),
            $bindings
        ));
    }

    /**
     * The statement with its parameters substituted in, for copying into a SQL
     * client.
     *
     * Only `?` outside a string or identifier literal is a placeholder. A
     * naive replacement gets `where note = 'why?'` wrong — it substitutes into
     * the literal and shifts every later parameter by one, producing a
     * statement that looks authoritative and is not. Where the placeholders
     * and the parameters do not line up exactly, the original is returned
     * unchanged rather than something plausible and wrong.
     *
     * This is a display convenience, not an escaping routine. Never execute
     * the result anywhere it matters.
     *
     * @param list<string> $bindings
     */
    protected function interpolate(string $sql, array $bindings): string
    {
        if (! $bindings) {
            return Values::printable($sql);
        }

        $interpolated = '';
        $consumed = 0;
        $quote = null;
        $length = strlen($sql);

        for ($i = 0; $i < $length; $i++) {
            $character = $sql[$i];

            if ($quote !== null) {
                $interpolated .= $character;

                if ($character === '\\' && $i + 1 < $length) {
                    // A backslash escape: whatever follows is literal, even a
                    // closing quote.
                    $interpolated .= $sql[++$i];
                } elseif ($character === $quote) {
                    if ($i + 1 < $length && $sql[$i + 1] === $quote) {
                        // A doubled quote is an escaped quote, not the end.
                        $interpolated .= $sql[++$i];
                    } else {
                        $quote = null;
                    }
                }

                continue;
            }

            if ($character === "'" || $character === '"' || $character === '`') {
                $quote = $character;
            } elseif ($character === '?') {
                if (! array_key_exists($consumed, $bindings)) {
                    return Values::printable($sql);
                }

                $interpolated .= $this->quote($bindings[$consumed++]);

                continue;
            }

            $interpolated .= $character;
        }

        if ($consumed !== count($bindings) || $quote !== null) {
            return Values::printable($sql);
        }

        return Values::truncate(Values::printable($interpolated), 2000);
    }

    protected function quote(string $binding): string
    {
        return is_numeric($binding) || in_array($binding, ['null', 'true', 'false'], true)
            ? $binding
            : "'".str_replace("'", "''", $binding)."'";
    }

    /**
     * Where in the application this query came from.
     */
    protected function origin(): ?string
    {
        $frames = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 50);

        foreach ($frames as $frame) {
            $file = $frame['file'] ?? null;

            if (! $file || $this->isInternalFrame($file)) {
                continue;
            }

            return $this->paths->reference($file, $frame['line'] ?? 0);
        }

        return null;
    }

    protected function isInternalFrame(string $file): bool
    {
        $normalised = str_replace('\\', '/', $file);

        if (str_starts_with($normalised, self::selfPath())) {
            return true;
        }

        foreach (self::INTERNAL_FRAMES as $needle) {
            if (str_contains($normalised, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * This extension's own runtime code, wherever it was installed.
     *
     * `src/` rather than the package root: the frames to skip are the ones
     * between the query and the application that ran it, and everything that
     * can appear there lives under `src/`.
     */
    protected static function selfPath(): string
    {
        static $path = null;

        return $path ??= str_replace('\\', '/', dirname(__DIR__)).'/';
    }

}
