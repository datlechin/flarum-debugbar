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

use Datlechin\FlarumDebugbar\Collector\CollectorInterface;
use Datlechin\FlarumDebugbar\DebugbarServiceProvider;
use Flarum\Api\Context;
use Flarum\Api\Schema;
use Illuminate\Contracts\Container\Container;

/**
 * The names of every registered collector, so the admin page can offer a
 * switch for each — including collectors this extension knows nothing about,
 * added by someone else through the extender.
 *
 * The list is read from the registry rather than from the running debug bar,
 * because the bar only holds the collectors that are switched *on*: reading it
 * would make a collector disappear from the settings page the moment it was
 * disabled, leaving no way to switch it back.
 */
class ForumFields
{
    public function __construct(
        protected Container $container,
    ) {
    }

    /**
     * @return array<Schema\Attribute>
     */
    public function __invoke(): array
    {
        return [
            Schema\Arr::make('debugbarCollectors')
                ->get(fn () => $this->collectorNames())
                // Nobody but an administrator can see the bar or configure it,
                // so nobody else needs this on every page they load.
                ->visible(fn (mixed $forum, Context $context) => $context->getActor()->isAdmin()),
        ];
    }

    /**
     * @return list<string>
     */
    protected function collectorNames(): array
    {
        $names = [];

        foreach ($this->container->make(DebugbarServiceProvider::COLLECTORS) as $class) {
            $collector = $this->container->make($class);

            if ($collector instanceof CollectorInterface) {
                $names[] = $collector->name();
            }
        }

        return array_values(array_unique($names));
    }
}
