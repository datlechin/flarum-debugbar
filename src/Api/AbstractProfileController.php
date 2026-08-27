<?php

/*
 * This file is part of datlechin/flarum-debugbar.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\FlarumDebugbar\Api;

use Datlechin\FlarumDebugbar\Debugbar;
use Datlechin\FlarumDebugbar\Storage\ProfileStorage;
use Flarum\Http\RequestUtil;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Shared behaviour for the endpoints that read profiles back out.
 */
abstract class AbstractProfileController implements RequestHandlerInterface
{
    public function __construct(
        protected ProfileStorage $storage,
        protected Debugbar $debugbar,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // Reading profiles must not write one. Without this, opening the bar
        // would append a row to the list it had just fetched, and every
        // refresh would append another.
        $this->debugbar->discard();

        // A profile holds settings, SQL, request headers and the actor's
        // groups. Debug mode is meant for development, but a forum left in it
        // is still a forum with members on it, so this is restricted to the
        // people who could read all of that anyway.
        RequestUtil::getActor($request)->assertAdmin();

        return $this->respond($request);
    }

    abstract protected function respond(ServerRequestInterface $request): ResponseInterface;
}
