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
 * One panel's worth of information about a request.
 *
 * A collector is resolved from the container once per request, so it may
 * type-hint whatever services it needs. Collectors that gather data as the
 * request runs (rather than reading it all at the end) accumulate it in
 * properties and hand it over in {@see collect()}; to be told when to start
 * listening, implement {@see SubscribesToEvents} as well.
 *
 * Register your own with the extender:
 *
 * ```php
 * (new Datlechin\FlarumDebugbar\Extend\Debugbar())
 *     ->collector(MyCollector::class)
 * ```
 */
interface CollectorInterface
{
    /**
     * A stable, machine-readable id, unique across all collectors.
     *
     * It is the key this collector's data appears under in a stored profile,
     * the id of the frontend tab that renders it, and the suffix of the
     * translation key used for the tab's title
     * (`<extension>.lib.debugbar.tabs.<name>`).
     */
    public function name(): string;

    /**
     * This request's data, as a JSON-serialisable array.
     *
     * Called exactly once, after the response has been produced but before it
     * has been sent, so both are available. Anything returned here is written
     * to disk and later handed to the frontend: keep it small, and mask
     * anything that should not be read back out of storage.
     */
    public function collect(ServerRequestInterface $request, ResponseInterface $response): array;
}
