<?php

/*
 * This file is part of datlechin/flarum-debugbar.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\FlarumDebugbar\Extend;

use Datlechin\FlarumDebugbar\Collector\CollectorInterface;
use Datlechin\FlarumDebugbar\DebugbarServiceProvider;
use Flarum\Extend\ExtenderInterface;
use Flarum\Extension\Extension;
use Illuminate\Contracts\Container\Container;

/**
 * Adds panels to the debug bar from another extension.
 *
 * ```php
 * // extend.php
 * return [
 *     (new Datlechin\FlarumDebugbar\Extend\Debugbar())
 *         ->collector(Acme\Collector\WidgetCollector::class),
 * ];
 * ```
 *
 * The collector is resolved from the container, so it may type-hint whatever
 * it needs. Its `name()` becomes the id of a tab in the bar; give the frontend
 * something to render there by registering a panel component under the same
 * id (see the README), or leave it and the bar will fall back to a generic
 * table of whatever `collect()` returned.
 *
 * Registering a collector for a debug bar that is switched off is harmless:
 * outside debug mode nothing is resolved and no listeners are attached.
 */
class Debugbar implements ExtenderInterface
{
    /**
     * @var list<class-string<CollectorInterface>>
     */
    private array $collectors = [];

    /**
     * @param class-string<CollectorInterface> $collector
     */
    public function collector(string $collector): self
    {
        $this->collectors[] = $collector;

        return $this;
    }

    public function extend(Container $container, ?Extension $extension = null): void
    {
        if (! $this->collectors) {
            return;
        }

        $container->extend(
            DebugbarServiceProvider::COLLECTORS,
            fn (array $existing) => [...$existing, ...$this->collectors]
        );
    }
}
