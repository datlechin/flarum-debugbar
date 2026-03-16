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
        $value = parent::get($key, $default);

        if ($value === null || $value === $default) {
            $this->collector->recordMiss($key);
        } else {
            $this->collector->recordHit($key, $value);
        }

        return $value;
    }

    public function put($key, $value, $ttl = null)
    {
        $this->collector->recordWrite($key);

        return parent::put($key, $value, $ttl);
    }

    public function forget($key)
    {
        $this->collector->recordDelete($key);

        return parent::forget($key);
    }

    public function has($key): bool
    {
        $result = parent::has($key);

        if ($result) {
            $this->collector->recordHit($key, null);
        } else {
            $this->collector->recordMiss($key);
        }

        return $result;
    }
}
