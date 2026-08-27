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

use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Recently profiled requests, newest first.
 *
 * The frontend already knows about requests it made itself. This is how it
 * learns about the ones it could not see: a form post that redirected, a
 * request from another tab, everything that happened before the page it is
 * running on was loaded.
 */
class ListProfilesController extends AbstractProfileController
{
    protected const DEFAULT_LIMIT = 25;

    protected const MAX_LIMIT = 100;

    protected function respond(ServerRequestInterface $request): ResponseInterface
    {
        $limit = $request->getQueryParams()['limit'] ?? null;

        $limit = is_numeric($limit)
            ? max(1, min(self::MAX_LIMIT, (int) $limit))
            : self::DEFAULT_LIMIT;

        return new JsonResponse(['data' => $this->storage->recent($limit)]);
    }
}
