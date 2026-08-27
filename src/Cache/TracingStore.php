<?php

/*
 * This file is part of datlechin/flarum-debugbar.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\FlarumDebugbar\Cache;

use Datlechin\FlarumDebugbar\Collector\CacheCollector;
use Illuminate\Contracts\Cache\Store;

/**
 * A cache store that reports what passes through it, and otherwise behaves
 * exactly like the store it wraps.
 *
 * The store is the right seam rather than the repository: `Repository` routes
 * `increment()`, `decrement()`, `forever()` and `touch()` straight to the
 * store, so a repository-level decorator never sees them. It also avoids the
 * trap that a repository decorator falls into, where `has()` is implemented in
 * terms of `get()` and every existence check is counted twice.
 *
 * @see TracingLockableStore for the variant used when the wrapped store
 *      supports atomic locks.
 */
class TracingStore implements Store
{
    public function __construct(
        protected Store $store,
        protected CacheCollector $collector,
    ) {
    }

    public function inner(): Store
    {
        return $this->store;
    }

    public function get($key): mixed
    {
        $value = $this->store->get($key);

        $this->collector->record(
            $value === null ? CacheCollector::MISS : CacheCollector::HIT,
            (string) $key
        );

        return $value;
    }

    public function many(array $keys): array
    {
        $values = $this->store->many($keys);

        foreach ($values as $key => $value) {
            $this->collector->record(
                $value === null ? CacheCollector::MISS : CacheCollector::HIT,
                (string) $key
            );
        }

        return $values;
    }

    public function put($key, $value, $seconds): bool
    {
        $this->collector->record(CacheCollector::WRITE, (string) $key);

        return $this->store->put($key, $value, $seconds);
    }

    public function putMany(array $values, $seconds): bool
    {
        foreach (array_keys($values) as $key) {
            $this->collector->record(CacheCollector::WRITE, (string) $key);
        }

        return $this->store->putMany($values, $seconds);
    }

    public function increment($key, $value = 1): int|bool
    {
        $this->collector->record(CacheCollector::WRITE, (string) $key);

        return $this->store->increment($key, $value);
    }

    public function decrement($key, $value = 1): int|bool
    {
        $this->collector->record(CacheCollector::WRITE, (string) $key);

        return $this->store->decrement($key, $value);
    }

    public function forever($key, $value): bool
    {
        $this->collector->record(CacheCollector::WRITE, (string) $key);

        return $this->store->forever($key, $value);
    }

    public function touch($key, $seconds): bool
    {
        $this->collector->record(CacheCollector::WRITE, (string) $key);

        return $this->store->touch($key, $seconds);
    }

    public function forget($key): bool
    {
        $this->collector->record(CacheCollector::FORGET, (string) $key);

        return $this->store->forget($key);
    }

    public function flush(): bool
    {
        $this->collector->record(CacheCollector::FLUSH, '*');

        return $this->store->flush();
    }

    public function getPrefix(): string
    {
        return $this->store->getPrefix();
    }
}
