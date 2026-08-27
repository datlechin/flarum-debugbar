<?php

/*
 * This file is part of datlechin/flarum-debugbar.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\FlarumDebugbar\Tests\unit\Extend;

use Datlechin\FlarumDebugbar\DebugbarServiceProvider;
use Datlechin\FlarumDebugbar\Extend\Debugbar as DebugbarExtender;
use Datlechin\FlarumDebugbar\Tests\unit\FakeCollector;
use Illuminate\Container\Container;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DebugbarTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        $this->container = new Container();
    }

    private function registerRegistry(): void
    {
        $this->container->singleton(DebugbarServiceProvider::COLLECTORS, fn () => ['Built\\In\\Collector']);
    }

    #[Test]
    public function it_adds_a_collector_to_the_registry(): void
    {
        $this->registerRegistry();

        (new DebugbarExtender())->collector(FakeCollector::class)->extend($this->container);

        $this->assertSame(
            ['Built\\In\\Collector', FakeCollector::class],
            $this->container->make(DebugbarServiceProvider::COLLECTORS)
        );
    }

    #[Test]
    public function it_works_when_the_extender_runs_before_the_registry_exists(): void
    {
        // Extension order decides whether another extension's extenders run
        // before or after this extension's service provider registers the
        // registry. The container holds an extension for an abstract it has
        // not seen yet and applies it on resolution, so neither order can
        // silently drop a collector — but only if nothing here resolves the
        // registry early.
        (new DebugbarExtender())->collector(FakeCollector::class)->extend($this->container);

        $this->registerRegistry();

        $this->assertContains(FakeCollector::class, $this->container->make(DebugbarServiceProvider::COLLECTORS));
    }

    #[Test]
    public function several_extensions_can_each_add_collectors(): void
    {
        $this->registerRegistry();

        (new DebugbarExtender())->collector('Acme\\One')->extend($this->container);
        (new DebugbarExtender())->collector('Acme\\Two')->collector('Acme\\Three')->extend($this->container);

        $this->assertSame(
            ['Built\\In\\Collector', 'Acme\\One', 'Acme\\Two', 'Acme\\Three'],
            $this->container->make(DebugbarServiceProvider::COLLECTORS)
        );
    }

    #[Test]
    public function an_extender_with_nothing_to_add_leaves_the_registry_alone(): void
    {
        $this->registerRegistry();

        (new DebugbarExtender())->extend($this->container);

        $this->assertSame(['Built\\In\\Collector'], $this->container->make(DebugbarServiceProvider::COLLECTORS));
    }
}
