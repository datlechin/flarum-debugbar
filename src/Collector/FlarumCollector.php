<?php

namespace Datlechin\FlarumDebugbar\Collector;

use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\Renderable;
use Flarum\Extension\ExtensionManager;
use Flarum\Foundation\Application;
use Illuminate\Contracts\Container\Container;

class FlarumCollector extends DataCollector implements Renderable
{
    public function __construct(
        protected Container $container,
    ) {
    }

    public function collect(): array
    {
        $extensions = $this->container->make(ExtensionManager::class);
        $enabled = $extensions->getEnabledExtensions();

        $extensionList = [];

        foreach ($enabled as $extension) {
            $extensionList[] = $extension->getId();
        }

        return [
            'flarum_version' => Application::VERSION,
            'php_version' => PHP_VERSION,
            'base_url' => (string) $this->container->make('flarum.config')->url(),
            'database_driver' => $this->getDatabaseDriver(),
            'queue_driver' => $this->getQueueDriver(),
            'session_driver' => $this->getSessionDriver(),
            'cache_driver' => $this->getCacheDriver(),
            'mail_driver' => $this->getMailDriver(),
            'extensions_enabled' => count($extensionList).' ('.implode(', ', $extensionList).')',
        ];
    }

    protected function getDatabaseDriver(): string
    {
        try {
            return $this->container->make('db')->connection()->getDriverName();
        } catch (\Throwable) {
            return 'unknown';
        }
    }

    protected function getQueueDriver(): string
    {
        try {
            $connection = $this->container->make('flarum.queue.connection');

            return match (true) {
                $connection instanceof \Illuminate\Queue\SyncQueue => 'sync',
                $connection instanceof \Illuminate\Queue\DatabaseQueue => 'database',
                default => $connection::class,
            };
        } catch (\Throwable) {
            return 'unknown';
        }
    }

    protected function getSessionDriver(): string
    {
        try {
            $handler = $this->container->make('session.handler');

            return match (true) {
                $handler instanceof \Illuminate\Session\FileSessionHandler => 'file',
                $handler instanceof \SessionHandler => 'file',
                $handler instanceof \Illuminate\Session\CacheBasedSessionHandler => 'cache',
                $handler instanceof \Illuminate\Session\DatabaseSessionHandler => 'database',
                default => class_basename($handler::class),
            };
        } catch (\Throwable) {
            return 'unknown';
        }
    }

    protected function getCacheDriver(): string
    {
        try {
            $store = $this->container->make('cache.store')->getStore();

            return match (true) {
                $store instanceof \Illuminate\Cache\FileStore => 'file',
                $store instanceof \Illuminate\Cache\ArrayStore => 'array',
                $store instanceof \Illuminate\Cache\RedisStore => 'redis',
                $store instanceof \Illuminate\Cache\MemcachedStore => 'memcached',
                $store instanceof \Illuminate\Cache\DatabaseStore => 'database',
                default => $store::class,
            };
        } catch (\Throwable) {
            return 'unknown';
        }
    }

    protected function getMailDriver(): string
    {
        try {
            $driver = $this->container->make('mail.driver');

            $name = is_object($driver) ? class_basename($driver::class) : (string) $driver;

            // Clean up common driver suffixes
            return strtolower(str_replace(['Driver', 'Transport'], '', $name)) ?: $name;
        } catch (\Throwable) {
            return 'unknown';
        }
    }

    public function getName(): string
    {
        return 'flarum';
    }

    public function getWidgets(): array
    {
        return [
            'flarum' => [
                'icon' => 'brand-php',
                'title' => 'Flarum',
                'widget' => 'PhpDebugBar.Widgets.VariableListWidget',
                'map' => 'flarum',
                'default' => '{}',
            ],
            'flarumversion' => [
                'icon' => 'brand-php',
                'tooltip' => 'Flarum Version',
                'map' => 'flarum.flarum_version',
                'default' => '',
            ],
        ];
    }
}
