<?php

/*
 * This file is part of datlechin/flarum-debugbar.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\FlarumDebugbar;

use Flarum\Extend;
use Flarum\Foundation\Config;

return [
    (new Extend\Conditional())
        ->when(
            fn (Config $config) => $config->inDebugMode(),
            fn () => [
                (new Extend\ServiceProvider())
                    ->register(Provider\DebugBarServiceProvider::class),

                (new Extend\Middleware('forum'))
                    ->insertAfter('flarum.forum.route_resolver', Middleware\InjectDebugBar::class), // @phpstan-ignore argument.type

                (new Extend\Middleware('admin'))
                    ->insertAfter('flarum.admin.route_resolver', Middleware\InjectDebugBar::class), // @phpstan-ignore argument.type

                (new Extend\Middleware('api'))
                    ->add(Middleware\ApiDebugBarMiddleware::class),

                (new Extend\Routes('forum'))
                    ->get('/debugbar/open', 'debugbar.open', Http\OpenHandlerController::class),

                (new Extend\Console())
                    ->command(Console\PublishAssetsCommand::class)
                    ->command(Console\ClearStorageCommand::class),
            ]
        ),
];
