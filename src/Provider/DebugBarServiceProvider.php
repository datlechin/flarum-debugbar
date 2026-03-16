<?php

namespace Datlechin\FlarumDebugbar\Provider;

use Datlechin\FlarumDebugbar\Cache\TracingCacheRepository;
use Datlechin\FlarumDebugbar\Collector\CacheCollector;
use Datlechin\FlarumDebugbar\Collector\EventCollector;
use Datlechin\FlarumDebugbar\Collector\ExtensionBootCollector;
use Datlechin\FlarumDebugbar\Collector\MailCollector;
use Datlechin\FlarumDebugbar\Collector\QueryCollector;
use Datlechin\FlarumDebugbar\DebugbarHelper;
use Datlechin\FlarumDebugbar\DebugBarFactory;
use DebugBar\DebugBar;
use Flarum\Foundation\AbstractServiceProvider;
use Flarum\Foundation\Paths;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;

class DebugBarServiceProvider extends AbstractServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(DebugBarFactory::class);
        $this->container->singleton(QueryCollector::class);
        $this->container->singleton(EventCollector::class);
        $this->container->singleton(CacheCollector::class);
        $this->container->singleton(MailCollector::class);
        $this->container->singleton(ExtensionBootCollector::class, function () {
            return new ExtensionBootCollector($this->container);
        });

        // Bind DebugBar for DI (used by OpenHandlerController)
        $this->container->singleton(DebugBar::class, function () {
            return $this->container->make(DebugBarFactory::class)->create();
        });

        // Listen for database queries via QueryExecuted event
        $this->registerQueryListener();
    }

    public function boot(Dispatcher $events): void
    {
        $this->publishDebugBarAssets();
        $this->registerEventListener($events);
        $this->registerCacheTracing();
        $this->registerMailListener($events);
        $this->initializeFacade();
    }

    protected function initializeFacade(): void
    {
        $debugbar = $this->container->make(DebugBarFactory::class)->create();

        DebugbarHelper::setDebugBar($debugbar);
    }

    protected function registerQueryListener(): void
    {
        $collector = $this->container->make(QueryCollector::class);

        try {
            $events = $this->container->make(Dispatcher::class);
            $events->listen(QueryExecuted::class, function (QueryExecuted $event) use ($collector) {
                $collector->onQueryExecuted($event);
            });
        } catch (\Throwable) {
        }
    }

    protected function registerEventListener(Dispatcher $events): void
    {
        $collector = $this->container->make(EventCollector::class);

        $events->listen('*', function (string $eventName, array $payload) use ($collector) {
            $collector->onWildcardEvent($eventName, $payload);
        });
    }

    protected function registerCacheTracing(): void
    {
        try {
            $this->container->extend('cache.store', function ($cacheRepository) {
                $collector = $this->container->make(CacheCollector::class);

                return new TracingCacheRepository(
                    $cacheRepository->getStore(),
                    $collector,
                );
            });
        } catch (\Throwable) {
        }
    }

    protected function registerMailListener(Dispatcher $events): void
    {
        $collector = $this->container->make(MailCollector::class);

        $events->listen(MessageSending::class, function (MessageSending $event) use ($collector) {
            $collector->onMessageSending($event);
        });

        $events->listen(MessageSent::class, function (MessageSent $event) use ($collector) {
            $collector->onMessageSent($event);
        });
    }

    protected function publishDebugBarAssets(): void
    {
        $paths = $this->container->make(Paths::class);

        $source = realpath(
            $paths->vendor.'/php-debugbar/php-debugbar/resources'
        );

        $destination = $paths->public.'/assets/debugbar';

        if (! $source) {
            return;
        }

        if (is_link($destination) && ! file_exists($destination)) {
            unlink($destination);
        }

        if (! file_exists($destination)) {
            @symlink($source, $destination);
        }
    }
}
