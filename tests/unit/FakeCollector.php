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

use Datlechin\FlarumDebugbar\Collector\CollectorInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Stands in for a collector another extension might register.
 */
class FakeCollector implements CollectorInterface
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        private string $name = 'fake',
        private array $data = [],
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function collect(ServerRequestInterface $request, ResponseInterface $response): array
    {
        return $this->data;
    }
}
