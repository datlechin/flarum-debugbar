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

use Datlechin\FlarumDebugbar\Cache\TracingStore;
use Flarum\Foundation\Application;
use Flarum\Foundation\Config;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Contracts\Container\Container;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Versions, drivers and limits — the paragraph you paste into a bug report.
 *
 * Every value here is read from configuration or from a service the request
 * has already used. Nothing is resolved for the sake of reporting it: making
 * the mail driver, for instance, would run its validation rules and add
 * queries to the very profile that is trying to describe the request.
 */
class EnvironmentCollector implements CollectorInterface
{
    public function __construct(
        protected Container $container,
        protected Config $config,
        protected SettingsRepositoryInterface $settings,
    ) {
    }

    public function name(): string
    {
        return 'environment';
    }

    public function collect(ServerRequestInterface $request, ResponseInterface $response): array
    {
        return [
            'groups' => [
                [
                    'name' => 'flarum',
                    'values' => [
                        'version' => Application::VERSION,
                        'url' => (string) $this->config->url(),
                        'debug' => $this->config->inDebugMode() ? 'on' : 'off',
                        'maintenance' => $this->config->maintenanceMode(),
                    ],
                ],
                [
                    'name' => 'php',
                    'values' => [
                        'version' => PHP_VERSION,
                        'sapi' => PHP_SAPI,
                        // Peak memory is not repeated here: it is on the
                        // profile itself, so the bar shows it in the summary
                        // and against every request in the picker — already
                        // formatted, and where it is actually read.
                        'memory_limit' => (string) ini_get('memory_limit'),
                        'max_execution_time' => (string) ini_get('max_execution_time'),
                        'opcache' => $this->opcacheStatus(),
                        'timezone' => date_default_timezone_get(),
                    ],
                ],
                [
                    'name' => 'drivers',
                    'values' => [
                        'database' => $this->probe(fn () => $this->databaseDriver()),
                        'cache' => $this->probe(fn () => $this->cacheDriver()),
                        'queue' => $this->config->queueDriver() ?? 'sync',
                        'session' => $this->probe(fn () => $this->sessionDriver()),
                        'mail' => (string) ($this->settings->get('mail_driver') ?: 'none'),
                    ],
                ],
            ],
        ];
    }

    protected function databaseDriver(): string
    {
        $connection = $this->container->make('db')->connection();

        $version = $connection->getPdo()->getAttribute(\PDO::ATTR_SERVER_VERSION);

        return $connection->getDriverName().' '.$version;
    }

    protected function cacheDriver(): string
    {
        $store = $this->container->make('cache.store')->getStore();

        // The debug bar wraps the store to watch it; report what is underneath,
        // not the wrapper, or the panel answers a question nobody asked.
        if ($store instanceof TracingStore) {
            $store = $store->inner();
        }

        return class_basename($store::class);
    }

    protected function sessionDriver(): string
    {
        return class_basename($this->container->make('session.handler')::class);
    }

    protected function opcacheStatus(): string
    {
        if (! function_exists('opcache_get_status')) {
            return 'not installed';
        }

        $status = @opcache_get_status(false);

        return is_array($status) && ($status['opcache_enabled'] ?? false) ? 'enabled' : 'disabled';
    }

    /**
     * Read a value that depends on the environment being correctly set up.
     *
     * A forum with a broken database connection or an unwritable session path
     * is exactly when someone opens the debug bar, so a failure here is
     * reported rather than swallowed — but it must not take the panel, or the
     * response, down with it.
     *
     * @param callable(): string $probe
     */
    protected function probe(callable $probe): string
    {
        try {
            return $probe();
        } catch (\Throwable $e) {
            return 'unavailable ('.$e->getMessage().')';
        }
    }
}
