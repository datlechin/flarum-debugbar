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
 * One stored profile, in full.
 */
class ShowProfileController extends AbstractProfileController
{
    protected function respond(ServerRequestInterface $request): ResponseInterface
    {
        $id = $request->getQueryParams()['id'] ?? '';

        $profile = is_string($id) ? $this->storage->find($id) : null;

        if (! $profile) {
            // Retention is bounded, so a profile the bar still has a row for
            // may already have been pruned. That is expected, not an error
            // worth alarming anyone about.
            return new JsonResponse(['errors' => [['status' => '404', 'code' => 'profile_not_found']]], 404);
        }

        return new JsonResponse(['data' => $profile->toArray()]);
    }
}
