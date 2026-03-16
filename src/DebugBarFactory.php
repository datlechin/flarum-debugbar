<?php

namespace Datlechin\FlarumDebugbar;

use DebugBar\DebugBar;
use DebugBar\StandardDebugBar;
use DebugBar\Storage\FileStorage;
use Flarum\Foundation\Config;
use Flarum\Foundation\Paths;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Contracts\Container\Container;

class DebugBarFactory
{
    protected ?DebugBar $debugbar = null;

    public function __construct(
        protected Config $config,
        protected Container $container,
    ) {
    }

    public function create(): ?DebugBar
    {
        if (! $this->config->inDebugMode()) {
            return null;
        }

        if ($this->debugbar) {
            return $this->debugbar;
        }

        $this->debugbar = new StandardDebugBar();

        // Set up file storage for request history and AJAX support
        $this->setupStorage();

        // Flarum-specific collectors
        $this->debugbar->addCollector(new Collector\FlarumCollector($this->container));

        // Database (event-driven, registered as singleton in service provider)
        $this->safeAddFromContainer(Collector\QueryCollector::class);

        // Request-aware collectors (request set later by middleware)
        $this->debugbar->addCollector(new Collector\RouteCollector());
        $this->debugbar->addCollector(new Collector\AuthCollector());
        $this->debugbar->addCollector(new Collector\ApiCollector());

        // Settings
        $this->safeAdd(fn () => new Collector\SettingsCollector(
            $this->container->make(SettingsRepositoryInterface::class)
        ));

        // Shared singleton collectors (registered in service provider)
        $this->safeAddFromContainer(Collector\EventCollector::class);
        $this->safeAddFromContainer(Collector\CacheCollector::class);
        $this->safeAddFromContainer(Collector\MailCollector::class);
        $this->safeAddFromContainer(Collector\ExtensionBootCollector::class);

        return $this->debugbar;
    }

    protected function setupStorage(): void
    {
        try {
            $paths = $this->container->make(Paths::class);
            $storagePath = $paths->storage.'/debugbar';

            if (! is_dir($storagePath)) {
                @mkdir($storagePath, 0755, true);
            }

            if (is_dir($storagePath)) {
                $storage = new FileStorage($storagePath);
                $storage->setAutoPrune(24, 10);
                $this->debugbar->setStorage($storage);
            }
        } catch (\Throwable) {
        }
    }

    protected function safeAdd(callable $factory): void
    {
        try {
            $this->debugbar->addCollector($factory());
        } catch (\Throwable) {
        }
    }

    protected function safeAddFromContainer(string $class): void
    {
        try {
            if ($this->container->bound($class)) {
                $this->debugbar->addCollector($this->container->make($class));
            }
        } catch (\Throwable) {
        }
    }
}
