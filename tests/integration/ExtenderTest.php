<?php

/*
 * This file is part of datlechin/flarum-debugbar.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\FlarumDebugbar\Tests\integration;

use Datlechin\FlarumDebugbar\Collector\CollectorInterface;
use Datlechin\FlarumDebugbar\Collector\SubscribesToEvents;
use Datlechin\FlarumDebugbar\Extend\Debugbar as DebugbarExtender;
use Datlechin\FlarumDebugbar\Http\Middleware\CollectProfile;
use Datlechin\FlarumDebugbar\Profile;
use Datlechin\FlarumDebugbar\Storage\ProfileStorage;
use Flarum\Foundation\Config;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Illuminate\Contracts\Events\Dispatcher;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * That another extension can add a panel.
 *
 * This is the extension point the README documents, and the only way to know
 * it works is to register a collector the way another extension would and look
 * for its data in a real profile.
 */
class ExtenderTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('datlechin-debugbar');
        $this->config('debug', true);
    }

    protected function profile(): Profile
    {
        $response = $this->send($this->request('GET', '/api/discussions'));
        $id = $response->getHeaderLine(CollectProfile::HEADER);

        return $this->app()->getContainer()->make(ProfileStorage::class)->find($id);
    }

    #[Test]
    public function a_registered_collector_appears_in_the_profile(): void
    {
        $this->extend((new DebugbarExtender())->collector(WidgetCollector::class));

        $data = $this->profile()->data;

        $this->assertArrayHasKey('widgets', $data);
        $this->assertSame(['count' => 3, 'source' => 'extender'], $data['widgets']);
    }

    #[Test]
    public function a_registered_collector_is_subscribed_to_events(): void
    {
        // A collector that gathers its data as the request runs is useless if
        // nothing ever calls `subscribe()`.
        $this->extend((new DebugbarExtender())->collector(ListeningCollector::class));

        $this->assertTrue($this->profile()->data['listening']['subscribed']);
    }

    #[Test]
    public function an_administrator_can_switch_a_registered_collector_off(): void
    {
        // The setting is a list of names, so it has to work for a collector
        // this extension has never heard of.
        $this->setting('datlechin-debugbar.disabled_collectors', '["widgets"]');
        $this->extend((new DebugbarExtender())->collector(WidgetCollector::class));

        $this->assertArrayNotHasKey('widgets', $this->profile()->data);
    }

    #[Test]
    public function a_switched_off_collector_never_subscribes(): void
    {
        // "A panel that is off collects nothing at all, not even a listener."
        $this->setting('datlechin-debugbar.disabled_collectors', '["listening"]');
        $this->extend((new DebugbarExtender())->collector(ListeningCollector::class));

        $this->assertArrayNotHasKey('listening', $this->profile()->data);
    }

    #[Test]
    public function a_collector_is_resolved_from_the_container(): void
    {
        // So it can type-hint whatever it needs, as the README says.
        $this->extend((new DebugbarExtender())->collector(InjectedCollector::class));

        $this->assertTrue($this->profile()->data['injected']['resolved']);
    }

    #[Test]
    public function the_registered_collector_is_offered_to_the_admin_page(): void
    {
        $this->extend((new DebugbarExtender())->collector(WidgetCollector::class));

        $response = $this->send($this->request('GET', '/api', ['authenticatedAs' => 1]));
        $body = json_decode($response->getBody(), true);

        $this->assertContains('widgets', $body['data']['attributes']['debugbarCollectors']);
    }

    #[Test]
    public function that_list_is_not_offered_to_anyone_else(): void
    {
        $body = json_decode($this->send($this->request('GET', '/api'))->getBody(), true);

        $this->assertArrayNotHasKey('debugbarCollectors', $body['data']['attributes']);
    }
}

class WidgetCollector implements CollectorInterface
{
    public function name(): string
    {
        return 'widgets';
    }

    public function collect(ServerRequestInterface $request, ResponseInterface $response): array
    {
        return ['count' => 3, 'source' => 'extender'];
    }
}

class ListeningCollector implements CollectorInterface, SubscribesToEvents
{
    protected bool $subscribed = false;

    public function name(): string
    {
        return 'listening';
    }

    public function subscribe(Dispatcher $events): void
    {
        $this->subscribed = true;
    }

    public function collect(ServerRequestInterface $request, ResponseInterface $response): array
    {
        return ['subscribed' => $this->subscribed];
    }
}

class InjectedCollector implements CollectorInterface
{
    public function __construct(
        protected Config $config,
        protected SettingsRepositoryInterface $settings,
    ) {
    }

    public function name(): string
    {
        return 'injected';
    }

    public function collect(ServerRequestInterface $request, ResponseInterface $response): array
    {
        return ['resolved' => $this->config->inDebugMode()];
    }
}
