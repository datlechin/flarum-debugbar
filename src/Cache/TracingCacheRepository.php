<?php

namespace Datlechin\FlarumDebugbar\Cache;

use Datlechin\FlarumDebugbar\Collector\CacheCollector;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Cache\Store;

class TracingCacheRepository extends Repository
{
    public function __construct(
        Store $store,
        protected CacheCollector $collector,
    ) {
        parent::__construct($store);
    }

    public function get($key, $default = null): mixed
    {
        $sentinel = new \stdClass();
        $value = parent::get($key, $sentinel);

        if ($value === $sentinel) {
            $this->collector->recordMiss($key);

            return is_callable($default) ? $default() : $default;
        }

        $this->collector->recordHit($key, $value);

        return $value;
    }

    public function many(array $keys)
    {
        $values = parent::many($keys);

        foreach ($values as $key => $value) {
            if ($value === null && ! $this->has($key)) {
                $this->collector->recordMiss($key);
            } else {
                $this->collector->recordHit($key, $value);
            }
        }

        return $values;
    }

    public function put($key, $value, $ttl = null)
    {
        $this->collector->recordWrite($key);

        return parent::put($key, $value, $ttl);
    }

    public function putMany(array $values, $ttl = null)
    {
        foreach (array_keys($values) as $key) {
            $this->collector->recordWrite($key);
        }

        return parent::putMany($values, $ttl);
    }

    public function forget($key)
    {
        $this->collector->recordDelete($key);

        return parent::forget($key);
    }

}
