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

use Flarum\Api\Resource\ForumResource;
use Flarum\Extend;
use Flarum\Foundation\Config;
use Flarum\Http\Middleware\InjectActorReference;

return [
    // Registered unconditionally, and cheap to leave registered: every binding
    // in it is lazy. It is what guarantees `resolve(Debugbar::class)` works
    // whenever this extension is enabled, so extension code can log to the bar
    // without first asking whether the bar exists. Outside debug mode the bar
    // it builds has no collectors and every method on it is a no-op.
    (new Extend\ServiceProvider())
        ->register(DebugbarServiceProvider::class),

    // The frontend is registered unconditionally too, for a less obvious
    // reason: `debug` lives in `config.php`, and changing it does not
    // recompile assets. If the bundle depended on it, turning debug on would
    // appear to do nothing until someone thought to clear the asset cache.
    // Instead the code always ships and stays dormant, because the payload
    // that wakes it up (see Frontend\AddDebugbar) is only ever written in
    // debug mode, and only for administrators.
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        ->css(__DIR__.'/less/forum.less'),

    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js')
        ->css(__DIR__.'/less/admin.less'),

    new Extend\Locales(__DIR__.'/locale'),

    (new Extend\ApiResource(ForumResource::class))
        ->fields(Api\ForumFields::class),

    (new Extend\Settings())
        ->default(Support\Settings::MAX_PROFILES, Support\Settings::DEFAULT_MAX_PROFILES)
        ->default(Support\Settings::DISABLED_COLLECTORS, '[]')
        ->default(Support\Settings::TRACE_QUERIES, true)
        ->default(Support\Settings::OPEN_BY_DEFAULT, false),

    (new Extend\Console())
        ->command(Console\ClearProfilesCommand::class),

    // Everything from here on costs something on every request, so none of it
    // is registered on a forum that is not being debugged.
    (new Extend\Conditional())
        ->when(
            fn (Config $config) => $config->inDebugMode(),
            fn () => [
                // Outermost in all three stacks. `InjectActorReference` is the
                // first middleware core pipes, and naming the class rather
                // than the `flarum.*.route_resolver` container binding keeps
                // this checkable by static analysis.
                (new Extend\Middleware('forum'))
                    ->insertBefore(InjectActorReference::class, Http\Middleware\CollectProfile::class)
                    ->add(Http\Middleware\CaptureRoute::class),

                (new Extend\Middleware('admin'))
                    ->insertBefore(InjectActorReference::class, Http\Middleware\CollectProfile::class)
                    ->add(Http\Middleware\CaptureRoute::class),

                (new Extend\Middleware('api'))
                    ->insertBefore(InjectActorReference::class, Http\Middleware\CollectProfile::class)
                    ->add(Http\Middleware\CaptureRoute::class),

                // Flarum's error handler is below the middleware above, so a
                // failed request arrives there already formatted into a 500
                // and the exception is gone. This is how the panel gets it.
                (new Extend\ErrorHandling())
                    ->reporter(ReportToDebugbar::class),

                (new Extend\Routes('api'))
                    ->get('/debugbar/profiles', 'debugbar.profiles.index', Api\ListProfilesController::class)
                    ->get('/debugbar/profiles/{id}', 'debugbar.profiles.show', Api\ShowProfileController::class)
                    ->delete('/debugbar/profiles', 'debugbar.profiles.clear', Api\ClearProfilesController::class),

                (new Extend\Frontend('forum'))
                    ->content(Frontend\AddDebugbar::class),

                (new Extend\Frontend('admin'))
                    ->content(Frontend\AddDebugbar::class),
            ]
        ),
];
