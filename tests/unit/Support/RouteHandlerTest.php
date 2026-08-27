<?php

/*
 * This file is part of datlechin/flarum-debugbar.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\FlarumDebugbar\Tests\unit\Support;

use Datlechin\FlarumDebugbar\Support\RouteHandler;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class RouteHandlerTest extends TestCase
{
    #[Test]
    public function it_reports_a_controller_class_as_itself(): void
    {
        $this->assertSame('Acme\\Controller', RouteHandler::describe('Acme\\Controller'));
    }

    #[Test]
    public function it_recovers_the_resource_and_endpoint_from_an_api_route(): void
    {
        // What `RouteHandlerFactory::toApiResource()` builds.
        $resourceClass = 'Flarum\\Api\\Resource\\DiscussionResource';
        $endpointName = 'index';
        $handler = function () use ($resourceClass, $endpointName) {
            return [$resourceClass, $endpointName];
        };

        $this->assertSame('DiscussionResource::index', RouteHandler::describe($handler));
    }

    #[Test]
    public function it_recovers_the_controller_from_a_controller_route(): void
    {
        $controller = 'Acme\\Http\\ShowWidgetController';
        $handler = function () use ($controller) {
            return $controller;
        };

        $this->assertSame('Acme\\Http\\ShowWidgetController', RouteHandler::describe($handler));
    }

    #[Test]
    public function it_names_the_frontend_behind_a_frontend_route(): void
    {
        // `toFrontend()` wraps a closure inside `toController()`'s closure, so
        // the name only appears one level down. Reporting `Closure` here would
        // mean reporting it for nearly every route in the forum.
        $frontend = 'forum';
        $inner = function () use ($frontend) {
            return $frontend;
        };
        $controller = $inner;
        $handler = function () use ($controller) {
            return $controller;
        };

        $this->assertSame('Frontend(forum)', RouteHandler::describe($handler));
    }

    #[Test]
    public function it_falls_back_to_closure_when_there_is_nothing_to_read(): void
    {
        $this->assertSame('Closure', RouteHandler::describe(fn () => null));
    }

    #[Test]
    public function it_handles_objects_arrays_and_nonsense(): void
    {
        $object = new \stdClass();

        $this->assertSame(\stdClass::class, RouteHandler::describe($object));
        $this->assertSame('Acme\\Controller::show', RouteHandler::describe(['Acme\\Controller', 'show']));
        $this->assertSame('unknown', RouteHandler::describe(null));
        $this->assertSame('unknown', RouteHandler::describe(42));
    }
}
