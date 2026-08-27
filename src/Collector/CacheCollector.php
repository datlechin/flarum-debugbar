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
 * Reads and writes against the cache.
 *
 * Fed by {@see \Datlechin\FlarumDebugbar\Cache\TracingStore}, which wraps the
 * store rather than the repository so that every path into the cache is seen —
 * including `increment()` and `forever()`, which the repository delegates
 * straight through.
 */
class CacheCollector implements CollectorInterface
{
    public const HIT = 'hit';
    public const MISS = 'miss';
    public const WRITE = 'write';
    public const FORGET = 'forget';
    public const FLUSH = 'flush';

    protected const LIMIT = 500;

    /**
     * @var list<array<string, mixed>>
     */
    protected array $operations = [];

    /**
     * @var array<string, int>
     */
    protected array $totals = [
        self::HIT => 0,
        self::MISS => 0,
        self::WRITE => 0,
        self::FORGET => 0,
        self::FLUSH => 0,
    ];

    protected int $dropped = 0;

    public function name(): string
    {
        return 'cache';
    }

    public function record(string $type, string $key): void
    {
        $this->totals[$type] = ($this->totals[$type] ?? 0) + 1;

        if (count($this->operations) >= self::LIMIT) {
            $this->dropped++;

            return;
        }

        $this->operations[] = [
            'type' => $type,
            'key' => $key,
            'time' => microtime(true),
        ];
    }

    public function collect(ServerRequestInterface $request, ResponseInterface $response): array
    {
        $reads = $this->totals[self::HIT] + $this->totals[self::MISS];

        return [
            'count' => count($this->operations) + $this->dropped,
            'dropped' => $this->dropped,
            'totals' => $this->totals,
            // Shown as a headline because a hit rate that has quietly fallen
            // to nothing is the usual symptom of a misconfigured cache, and it
            // is invisible in a list of individual operations.
            'hitRate' => $reads > 0 ? $this->totals[self::HIT] / $reads : null,
            'operations' => $this->operations,
        ];
    }
}
