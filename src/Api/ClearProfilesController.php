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
 * Throw away the stored history.
 */
class ClearProfilesController extends AbstractProfileController
{
    protected function respond(ServerRequestInterface $request): ResponseInterface
    {
        return new JsonResponse(['data' => ['cleared' => $this->storage->clear()]]);
    }
}
