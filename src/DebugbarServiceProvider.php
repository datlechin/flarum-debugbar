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

use Datlechin\FlarumDebugbar\Cache\TracingLockableStore;
use Datlechin\FlarumDebugbar\Cache\TracingStore;
use Datlechin\FlarumDebugbar\Collector\CacheCollector;
use Datlechin\FlarumDebugbar\Collector\CollectorInterface;
use Datlechin\FlarumDebugbar\Collector\EnvironmentCollector;
use Datlechin\FlarumDebugbar\Collector\EventCollector;
use Datlechin\FlarumDebugbar\Collector\ExtensionCollector;
use Datlechin\FlarumDebugbar\Collector\MailCollector;
use Datlechin\FlarumDebugbar\Collector\MessageCollector;
use Datlechin\FlarumDebugbar\Collector\QueryCollector;
use Datlechin\FlarumDebugbar\Collector\RequestCollector;
use Datlechin\FlarumDebugbar\Collector\SettingsCollector;
use Datlechin\FlarumDebugbar\Collector\SubscribesToEvents;
use Datlechin\FlarumDebugbar\Collector\TimelineCollector;
use Datlechin\FlarumDebugbar\Storage\FileProfileStorage;
use Datlechin\FlarumDebugbar\Storage\ProfileStorage;
use Datlechin\FlarumDebugbar\Support\Settings;
use Datlechin\FlarumDebugbar\Support\SourcePath;
use Flarum\Foundation\AbstractServiceProvider;
use Flarum\Foundation\Config;
use Flarum\Foundation\Paths;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Psr\Log\LoggerInterface;

/**
 * Wires the debug bar up.
 *
 * This provider is registered whether or not the forum is in debug mode, so
 * that {@see Debugbar} can always be resolved and extension code never has to
 * guard a call to it. Outside debug mode the bar is built with no collectors,
 * nothing subscribes to anything, and the cache is left alone.
 */
class DebugbarServiceProvider extends AbstractServiceProvider
{
    /**
     * The registry of collector classes, extended by
     * {@see \Datlechin\FlarumDebugbar\Extend\Debugbar}.
     */
    public const COLLECTORS = 'datlechin-debugbar.collectors';

    /**
     * When the request started. A binding rather than a value read twice, so
     * that the bar's own clock and the timeline's agree exactly.
     */
    public const STARTED_AT = 'datlechin-debugbar.started_at';

    public function register(): void
    {
        $this->container->singleton(self::STARTED_AT, fn () => Debugbar::requestStartTime());

        /**
         * The panels the bar ships with, in the order they appear.
         *
         * @return list<class-string<CollectorInterface>>
         */
        $this->container->singleton(self::COLLECTORS, fn () => [
            TimelineCollector::class,
            QueryCollector::class,
            MessageCollector::class,
            RequestCollector::class,
            EventCollector::class,
            CacheCollector::class,
            MailCollector::class,
            SettingsCollector::class,
            ExtensionCollector::class,
            EnvironmentCollector::class,
        ]);

        // Two collectors take values rather than services, so they are built
        // here instead of being autowired.
        $this->container->singleton(
            TimelineCollector::class,
            fn (Container $container) => new TimelineCollector($container->make(self::STARTED_AT))
        );

        $this->container->singleton(
            QueryCollector::class,
            fn (Container $container) => new QueryCollector(
                $container->make(SourcePath::class),
                (bool) $container->make(SettingsRepositoryInterface::class)->get(Settings::TRACE_QUERIES, true),
            )
        );

        $this->container->singleton(
            ProfileStorage::class,
            fn (Container $container) => new FileProfileStorage(
                $container->make(Paths::class)->storage.'/debugbar',
                Settings::maxProfiles($container->make(SettingsRepositoryInterface::class)),
            )
        );

        $this->container->singleton(Debugbar::class, function (Container $container) {
            $debugbar = new Debugbar(
                $container->make(Config::class)->inDebugMode(),
                $container->make(self::STARTED_AT),
            );

            if ($debugbar->isEnabled()) {
                $this->addCollectors($debugbar, $container);
            }

            return $debugbar;
        });
    }

    public function boot(Container $container, Dispatcher $events, LoggerInterface $log): void
    {
        $debugbar = $container->make(Debugbar::class);

        if (! $debugbar->isEnabled()) {
            return;
        }

        foreach ($debugbar->collectors() as $collector) {
            if ($collector instanceof SubscribesToEvents) {
                $collector->subscribe($events);
            }
        }

        $this->traceCache($container, $debugbar, $log);
    }

    protected function addCollectors(Debugbar $debugbar, Container $container): void
    {
        $settings = $container->make(SettingsRepositoryInterface::class);
        $disabled = Settings::disabledCollectors($settings);

        foreach ($container->make(self::COLLECTORS) as $class) {
            $collector = $container->make($class);

            if (! $collector instanceof CollectorInterface) {
                continue;
            }

            if (in_array($collector->name(), $disabled, true)) {
                continue;
            }

            $debugbar->addCollector($collector);
        }
    }

    /**
     * Watch the cache by swapping the repository's store for one that reports
     * what passes through it.
     *
     * The replacement keeps the repository's own configuration — its default
     * TTL and its event dispatcher — because this must change what the debug
     * bar can see and nothing else about how the forum behaves.
     */
    protected function traceCache(Container $container, Debugbar $debugbar, LoggerInterface $log): void
    {
        $collector = $debugbar->collector(CacheCollector::class);

        if (! $collector instanceof CacheCollector) {
            return;
        }

        try {
            $container->extend('cache.store', function (Repository $repository) use ($collector) {
                $store = $repository->getStore();

                if ($store instanceof TracingStore) {
                    return $repository;
                }

                $traced = new Repository($store instanceof LockProvider
                    ? new TracingLockableStore($store, $collector)
                    : new TracingStore($store, $collector));

                $traced->setDefaultCacheTime($repository->getDefaultCacheTime());

                if ($dispatcher = $repository->getEventDispatcher()) {
                    $traced->setEventDispatcher($dispatcher);
                }

                return $traced;
            });
        } catch (\Throwable $e) {
            // A forum whose cache cannot be decorated is still a forum that
            // should serve requests; it just loses one panel.
            $log->warning('[flarum-debugbar] could not trace the cache: '.$e->getMessage(), ['exception' => $e]);
        }
    }
}
