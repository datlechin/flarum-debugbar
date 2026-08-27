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

use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockProvider;

/**
 * {@see TracingStore} for stores that provide atomic locks.
 *
 * Callers ask the *store* whether locking is available
 * (`$cache->getStore() instanceof LockProvider`) — core's email notification
 * job does exactly this, and falls back to a non-atomic send when the answer
 * is no. A decorator that dropped the interface would therefore silently
 * change how the forum behaves whenever the debug bar was switched on, which
 * is the one thing a debugging tool must never do.
 */
class TracingLockableStore extends TracingStore implements LockProvider
{
    public function lock($name, $seconds = 0, $owner = null): Lock
    {
        return $this->lockProvider()->lock($name, $seconds, $owner);
    }

    public function restoreLock($name, $owner): Lock
    {
        return $this->lockProvider()->restoreLock($name, $owner);
    }

    protected function lockProvider(): LockProvider
    {
        \assert($this->store instanceof LockProvider);

        return $this->store;
    }
}
