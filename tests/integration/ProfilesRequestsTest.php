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

use Datlechin\FlarumDebugbar\Http\Middleware\CaptureRoute;
use Datlechin\FlarumDebugbar\Http\Middleware\CollectProfile;
use Datlechin\FlarumDebugbar\Profile;
use Datlechin\FlarumDebugbar\Storage\ProfileStorage;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;

/**
 * That the extenders actually wire up.
 *
 * The unit tests prove each collector and the storage work on their own. What
 * they cannot prove is that the middleware lands in the stack at all, in the
 * right position, in every frontend — which is the part that silently stops
 * working when Flarum moves something.
 */
class ProfilesRequestsTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('datlechin-debugbar');

        // Everything expensive is behind `Extend\Conditional` on debug mode.
        $this->config('debug', true);

        $this->prepareDatabase([
            'users' => [$this->normalUser()],
        ]);
    }

    protected function profileFor(ResponseInterface $response): Profile
    {
        $id = $response->getHeaderLine(CollectProfile::HEADER);

        $this->assertNotSame('', $id, 'the response carried no profile id');

        $profile = $this->app()->getContainer()->make(ProfileStorage::class)->find($id);

        $this->assertInstanceOf(Profile::class, $profile, "no profile was stored under {$id}");

        return $profile;
    }

    #[Test]
    public function a_forum_page_is_profiled(): void
    {
        $response = $this->send($this->request('GET', '/'));

        $this->assertEquals(200, $response->getStatusCode());

        $profile = $this->profileFor($response);

        $this->assertSame('GET', $profile->method);
        $this->assertSame('/', $profile->uri);
        $this->assertSame(200, $profile->status);
        $this->assertGreaterThan(0, $profile->memory);
    }

    #[Test]
    public function an_api_request_is_profiled(): void
    {
        $response = $this->send($this->request('GET', '/api/discussions'));

        $this->assertEquals(200, $response->getStatusCode());

        $route = $this->profileFor($response)->data['request']['route'];

        // Also the only test that exercises `RouteHandler::describe` against a
        // closure Flarum really built: every API route is wrapped by
        // `toApiResource()`, and reporting `Closure` for all of them would
        // make the panel useless.
        $this->assertSame('discussions.index', $route['name']);
        $this->assertSame('DiscussionResource::index', $route['handler']);
    }

    #[Test]
    public function an_admin_page_is_profiled(): void
    {
        $response = $this->send($this->request('GET', '/admin', ['authenticatedAs' => 1]));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame('Frontend(admin)', $this->profileFor($response)->data['request']['route']['handler']);
    }

    #[Test]
    public function every_enabled_collector_contributes(): void
    {
        $data = $this->profileFor($this->send($this->request('GET', '/')))->data;

        $this->assertSame(
            ['timeline', 'queries', 'messages', 'request', 'events', 'cache', 'mail', 'settings', 'extensions', 'environment'],
            array_keys($data)
        );
    }

    #[Test]
    public function nothing_is_profiled_outside_debug_mode(): void
    {
        // The whole point of the `Conditional`: a forum that is not being
        // debugged pays for none of this.
        $this->config('debug', false);

        $response = $this->send($this->request('GET', '/'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertFalse($response->hasHeader(CollectProfile::HEADER));
    }

    #[Test]
    public function the_resolved_route_reaches_the_request_panel(): void
    {
        // Only reachable from the bottom of the middleware stack: PSR-7
        // requests are immutable, so the outermost middleware never sees the
        // route attributes. This proves `CaptureRoute` is in the stack.
        $request = $this->profileFor($this->send($this->request('GET', '/all')))->data['request'];

        $this->assertSame('index', $request['route']['name']);
        $this->assertSame('Frontend(forum)', $request['route']['handler']);
        $this->assertFalse($request['route']['internal']);
    }

    #[Test]
    public function the_timeline_accounts_for_the_whole_request(): void
    {
        $timeline = $this->profileFor($this->send($this->request('GET', '/')))->data['timeline'];
        $names = array_column($timeline['measures'], 'name');

        // Boot is recorded by the outermost middleware and the route span by
        // the innermost, so all three being present proves both are in the
        // stack and in the right order.
        $this->assertContains(CollectProfile::BOOT, $names);
        $this->assertContains(CollectProfile::REQUEST, $names);
        $this->assertContains(CaptureRoute::ROUTE, $names);

        $spans = array_column($timeline['measures'], null, 'name');

        $this->assertGreaterThan($spans[CollectProfile::REQUEST]['start'], $spans[CaptureRoute::ROUTE]['start']);
    }

    #[Test]
    public function the_queries_a_page_ran_are_recorded(): void
    {
        $queries = $this->profileFor($this->send($this->request('GET', '/')))->data['queries'];

        $this->assertGreaterThan(0, $queries['count']);
        $this->assertNotEmpty($queries['queries'][0]['sql']);
    }

    #[Test]
    public function a_page_load_produces_exactly_one_profile(): void
    {
        // A frontend page renders by dispatching API requests to itself
        // through `Api\Client`, down this same middleware stack. Those are
        // part of the page's cost, not requests of their own, and profiling
        // them separately would fill the history with rows nobody asked for.
        $storage = $this->app()->getContainer()->make(ProfileStorage::class);
        $storage->clear();

        $this->send($this->request('GET', '/'));

        $this->assertCount(1, $storage->recent(50));
    }

    #[Test]
    public function an_internal_dispatch_is_folded_into_the_parent_timeline(): void
    {
        $timeline = $this->profileFor($this->send($this->request('GET', '/')))->data['timeline'];
        $labels = array_column($timeline['measures'], 'label');

        $this->assertContains('GET /', $labels, 'the forum document fetch should appear as a span');
    }

    #[Test]
    public function the_actor_reaches_the_request_panel(): void
    {
        $actor = $this->profileFor($this->send($this->request('GET', '/', ['authenticatedAs' => 1])))->data['request']['actor'];

        $this->assertFalse($actor['isGuest']);
        $this->assertSame(1, $actor['id']);
    }

    #[Test]
    public function retention_bounds_what_is_kept(): void
    {
        $this->setting('datlechin-debugbar.max_profiles', 3);

        $storage = $this->app()->getContainer()->make(ProfileStorage::class);
        $storage->clear();

        foreach (range(1, 5) as $ignored) {
            $this->send($this->request('GET', '/api/discussions'));
        }

        $this->assertCount(3, $storage->recent(50));
    }
}
