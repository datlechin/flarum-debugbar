<?php

/*
 * This file is part of datlechin/flarum-debugbar.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\FlarumDebugbar\Tests\unit;

use Laminas\Diactoros\Response;
use Laminas\Diactoros\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Collectors are handed the request and the response, whether or not they use
 * them. This keeps the ceremony out of the tests that do not care.
 */
trait MakesHttpMessages
{
    /**
     * @param array<string, string|string[]> $headers
     */
    protected function request(string $method = 'GET', string $uri = '/', array $headers = []): ServerRequestInterface
    {
        return new ServerRequest(
            serverParams: [],
            uploadedFiles: [],
            uri: $uri,
            method: $method,
            body: 'php://temp',
            headers: $headers,
        );
    }

    /**
     * @param array<string, string|string[]> $headers
     */
    protected function response(int $status = 200, array $headers = []): ResponseInterface
    {
        return new Response('php://temp', $status, $headers);
    }
}
