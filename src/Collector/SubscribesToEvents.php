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

use Illuminate\Contracts\Events\Dispatcher;

/**
 * Implemented by collectors that gather their data from events as the request
 * runs, rather than reading it all at collection time.
 *
 * {@see subscribe()} is called once, when the debug bar boots, and only for
 * collectors the administrator has left switched on — so a collector that is
 * off costs nothing at all, not even a listener.
 */
interface SubscribesToEvents
{
    public function subscribe(Dispatcher $events): void;
}
