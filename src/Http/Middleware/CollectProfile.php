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

use Datlechin\FlarumDebugbar\Debugbar;
use Datlechin\FlarumDebugbar\Storage\ProfileStorage;
use Flarum\Http\RequestUtil;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

/**
 * Wraps the whole request, stores what the collectors saw, and tells the
 * browser where to find it.
 *
 * This sits at the very top of the middleware stack — ahead of even the error
 * handler — for two reasons. It measures the parts of a slow request that are
 * otherwise invisible (session start, authentication, CSRF), and it still sees
 * a response when the request failed, so a 500 gets a profile like anything
 * else. That is the request people most want to look at.
 */
class CollectProfile implements MiddlewareInterface
{
    /**
     * Names the profile for the request that carried it. The frontend reads
     * this off every response to build its request list.
     */
    public const HEADER = 'X-Debugbar-Id';

    /**
     * Span names for the request's own shape. They are stable so the frontend
     * can give them translated labels; anything it does not recognise falls
     * back to the label recorded here.
     */
    public const BOOT = 'flarum.boot';
    public const REQUEST = 'flarum.request';

    public function __construct(
        protected Debugbar $debugbar,
        protected ProfileStorage $storage,
        protected LoggerInterface $log,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (! $this->debugbar->isEnabled()) {
            return $handler->handle($request);
        }

        // A frontend page renders by dispatching API requests to itself
        // through `Api\Client`, which runs them down this same stack. They are
        // part of the page's cost, not requests in their own right, so they
        // are folded into the parent profile as a timeline span instead of
        // being profiled (and stored, and listed) separately.
        if (RequestUtil::isInternal($request)) {
            return $this->debugbar->measure(
                $request->getMethod().' '.$request->getUri()->getPath(),
                fn () => $handler->handle($request)
            );
        }

        // Everything before this point — autoloading, the container, every
        // extender, every service provider — is already over, so it can only
        // be recorded after the fact. On a slow request it is very often the
        // largest single span, and it is the one nothing else can show.
        $this->debugbar->recordMeasure(self::BOOT, 'Boot', $this->debugbar->startedAt(), microtime(true));

        $this->debugbar->startMeasure(self::REQUEST, 'Request');

        try {
            $response = $handler->handle($request);
        } catch (\Throwable $e) {
            // Nothing below us formatted this, so it is on its way to the
            // SAPI. Record it, then let it continue untouched.
            $this->debugbar->exception($e);

            throw $e;
        } finally {
            $this->debugbar->stopMeasure(self::REQUEST);
        }

        return $this->record($request, $response);
    }

    protected function record(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $profile = $this->debugbar->collect($request, $response);

            if (! $profile) {
                return $response;
            }

            $this->storage->save($profile);

            return $response->withHeader(self::HEADER, $profile->id);
        } catch (\Throwable $e) {
            // Failing to describe a request must never fail the request. The
            // forum keeps working; the log says why the bar looks empty.
            $this->log->warning('[flarum-debugbar] could not record a profile: '.$e->getMessage(), ['exception' => $e]);

            return $response;
        }
    }
}
