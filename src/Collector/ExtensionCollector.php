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

use Flarum\Extension\ExtensionManager;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Which extensions are installed, which of those are running, and at what
 * version — the first thing anyone asks for when a bug cannot be reproduced.
 */
class ExtensionCollector implements CollectorInterface
{
    public function __construct(
        protected ExtensionManager $extensions,
    ) {
    }

    public function name(): string
    {
        return 'extensions';
    }

    public function collect(ServerRequestInterface $request, ResponseInterface $response): array
    {
        $enabled = $this->extensions->getEnabledExtensions();
        $extensions = [];

        foreach ($this->extensions->getExtensions() as $extension) {
            $extensions[] = [
                'id' => $extension->getId(),
                'title' => $extension->getTitle(),
                'version' => $extension->getVersion(),
                'enabled' => isset($enabled[$extension->getId()]),
                'dependencies' => array_values($extension->getExtensionDependencyIds()),
            ];
        }

        usort($extensions, function (array $a, array $b) {
            // Enabled first: a list of forty extensions of which six are
            // running should not make the reader hunt for the six.
            return [$b['enabled'], $a['id']] <=> [$a['enabled'], $b['id']];
        });

        return [
            'count' => count($extensions),
            'enabled' => count($enabled),
            'extensions' => $extensions,
        ];
    }
}
