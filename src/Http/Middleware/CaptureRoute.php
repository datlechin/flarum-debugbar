<?php

/*
 * This file is part of datlechin/flarum-debugbar.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\FlarumDebugbar\Http\Middleware;

use Datlechin\FlarumDebugbar\Collector\RequestCollector;
use Datlechin\FlarumDebugbar\Debugbar;
use Flarum\Http\RequestUtil;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Hands the fully-resolved request to the collector that describes it.
 *
 * PSR-7 requests are immutable: the route, the session and the authenticated
 * actor are attributes on clones made progressively deeper in the stack, and
 * {@see CollectProfile} — which sits at the top — only ever holds the
 * original. This runs at the bottom, where every middleware has had its say,
 * and passes that final request back up.
 *
 * It is registered with `add()` rather than by position, so it stays at the
 * bottom of the stack no matter what else the forum has installed, and makes
 * no assumption about which middleware precedes it.
 */
class CaptureRoute implements MiddlewareInterface
{
    /**
     * The span covering the route handler alone. Whatever is left between this
     * and {@see CollectProfile::REQUEST} is the middleware stack — session,
     * authentication, CSRF — which is otherwise invisible.
     */
    public const ROUTE = 'flarum.route';

    public function __construct(
        protected Debugbar $debugbar,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // An internal API dispatch is a detail of how the page was rendered.
        // Letting it through here would overwrite the page's own route with
        // whichever API endpoint happened to be fetched last, and would add a
        // second `flarum.route` span inside the first.
        if (RequestUtil::isInternal($request)) {
            return $handler->handle($request);
        }

        $collector = $this->debugbar->collector(RequestCollector::class);

        if ($collector instanceof RequestCollector) {
            $collector->observe($request);
        }

        $this->debugbar->startMeasure(self::ROUTE, 'Route handler');

        try {
            return $handler->handle($request);
        } finally {
            $this->debugbar->stopMeasure(self::ROUTE);
        }
    }
}
