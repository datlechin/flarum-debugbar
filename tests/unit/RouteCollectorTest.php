<?php

namespace Datlechin\FlarumDebugbar\Tests\unit;

use Datlechin\FlarumDebugbar\Collector\RouteCollector;
use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\Uri;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class RouteCollectorTest extends TestCase
{
    #[Test]
    public function it_returns_empty_when_no_request(): void
    {
        $collector = new RouteCollector();

        $this->assertEmpty($collector->collect());
    }

    #[Test]
    public function it_collects_route_data_from_request(): void
    {
        $collector = new RouteCollector();

        $request = (new ServerRequest())
            ->withMethod('GET')
            ->withUri(new Uri('http://flarum.test/d/1-hello'))
            ->withAttribute('routeName', 'discussion')
            ->withAttribute('routeHandler', 'SomeController')
            ->withAttribute('routeParameters', ['id' => '1']);

        $collector->setRequest($request);
        $data = $collector->collect();

        $this->assertEquals('GET', $data['method']);
        $this->assertEquals('/d/1-hello', $data['uri']);
        $this->assertEquals('discussion', $data['route']);
        $this->assertEquals('SomeController', $data['handler']);
        $this->assertStringContainsString('"id"', $data['parameters']);
    }

    #[Test]
    public function it_resolves_closure_with_frontend_variable(): void
    {
        $collector = new RouteCollector();

        $frontend = 'forum';
        $handler = function () use ($frontend) {
            return $frontend;
        };

        // Wrap in outer closure like toController does
        $controller = $handler;
        $outerHandler = function () use ($controller) {
            return $controller;
        };

        $request = (new ServerRequest())
            ->withMethod('GET')
            ->withUri(new Uri('http://flarum.test/'))
            ->withAttribute('routeName', 'default')
            ->withAttribute('routeHandler', $outerHandler)
            ->withAttribute('routeParameters', []);

        $collector->setRequest($request);
        $data = $collector->collect();

        $this->assertEquals('Frontend(forum)', $data['handler']);
    }

    #[Test]
    public function it_returns_correct_name(): void
    {
        $this->assertEquals('route', (new RouteCollector())->getName());
    }
}
