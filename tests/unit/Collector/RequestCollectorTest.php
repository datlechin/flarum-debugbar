<?php

/*
 * This file is part of datlechin/flarum-debugbar.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\FlarumDebugbar\Tests\unit\Collector;

use Datlechin\FlarumDebugbar\Collector\RequestCollector;
use Datlechin\FlarumDebugbar\Tests\unit\MakesHttpMessages;
use Flarum\Group\Group;
use Flarum\Http\RequestUtil;
use Flarum\User\Guest;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Collection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class RequestCollectorTest extends TestCase
{
    use MakesHttpMessages;

    #[Test]
    public function it_describes_the_request_and_the_response(): void
    {
        $collector = new RequestCollector();

        $data = $collector->collect($this->request('POST', '/api/discussions'), $this->response(201));

        $this->assertSame('POST', $data['method']);
        $this->assertStringContainsString('/api/discussions', $data['uri']);
        $this->assertSame(201, $data['status']);
    }

    #[Test]
    public function it_reports_the_resolved_route(): void
    {
        $collector = new RequestCollector();

        $collector->observe(
            $this->request()
                ->withAttribute('routeName', 'discussion')
                ->withAttribute('routeHandler', 'Acme\\ShowDiscussionController')
                ->withAttribute('routeParameters', ['id' => '12', 'near' => 3])
        );

        $route = $collector->collect($this->request(), $this->response())['route'];

        $this->assertSame('discussion', $route['name']);
        $this->assertSame('Acme\\ShowDiscussionController', $route['handler']);
        $this->assertSame(['id' => '12', 'near' => '3'], $route['parameters']);
        $this->assertFalse($route['internal']);
    }

    #[Test]
    public function it_still_describes_a_request_that_never_reached_the_bottom_of_the_stack(): void
    {
        // A 404 or a CSRF rejection never gets as far as `observe()`. What can
        // still be reported should be.
        $collector = new RequestCollector();

        $data = $collector->collect($this->request('GET', '/nope'), $this->response(404));

        $this->assertSame(404, $data['status']);
        $this->assertNull($data['route']['name']);
        $this->assertSame('unknown', $data['route']['handler']);
    }

    #[Test]
    public function it_reports_the_actor_that_middleware_resolved(): void
    {
        $collector = new RequestCollector();
        $collector->observe(RequestUtil::withActor($this->request(), new Guest()));

        $actor = $collector->collect($this->request(), $this->response())['actor'];

        $this->assertTrue($actor['isGuest']);
        $this->assertNull($actor['username']);
    }

    #[Test]
    public function it_does_not_load_the_groups_relation_to_describe_the_actor(): void
    {
        // Reading `groups` — or `isAdmin()`, which reads it — would issue a
        // query from inside the collector and add it to the very profile that
        // is meant to describe the request. There is no database in a unit
        // test, so a collector that reached for it would fail outright here.
        $collector = new RequestCollector();
        $collector->observe(RequestUtil::withActor($this->request(), new Guest()));

        $actor = $collector->collect($this->request(), $this->response())['actor'];

        $this->assertArrayNotHasKey('isAdmin', $actor);
        $this->assertArrayNotHasKey('groups', $actor);
    }

    #[Test]
    public function it_reports_groups_that_were_already_loaded(): void
    {
        $group = new Group();
        $group->id = Group::ADMINISTRATOR_ID;
        $group->name_singular = 'Admin';

        // A real user rather than a guest: `Guest` overrides the groups
        // accessor to fetch its own group, so it cannot stand in for one whose
        // relation is already populated.
        $user = new User();
        $user->id = 1;
        $user->username = 'admin';
        $user->setRelation('groups', new Collection([$group]));

        $collector = new RequestCollector();
        $collector->observe(RequestUtil::withActor($this->request(), $user));

        $actor = $collector->collect($this->request(), $this->response())['actor'];

        $this->assertTrue($actor['isAdmin']);
        $this->assertSame(['Admin'], $actor['groups']);
    }

    #[Test]
    public function it_says_so_rather_than_failing_when_there_is_no_actor_yet(): void
    {
        // Before `InjectActorReference` has run there is no reference to read,
        // and asking for the actor would fatal rather than return a guest.
        $collector = new RequestCollector();

        $actor = $collector->collect($this->request(), $this->response())['actor'];

        $this->assertSame(['authentication' => 'unresolved'], $actor);
    }

    #[Test]
    public function it_reports_how_the_actor_was_authenticated(): void
    {
        foreach ([
            'apiKey' => 'api key',
            'bypassCsrfToken' => 'access token',
            'session' => 'session',
        ] as $attribute => $expected) {
            $collector = new RequestCollector();
            $collector->observe(RequestUtil::withActor($this->request(), new Guest())->withAttribute($attribute, true));

            $this->assertSame($expected, $collector->collect($this->request(), $this->response())['actor']['authentication']);
        }
    }

    #[Test]
    public function it_marks_an_internal_dispatch(): void
    {
        $collector = new RequestCollector();
        $collector->observe(RequestUtil::withInternal($this->request()));

        $this->assertTrue($collector->collect($this->request(), $this->response())['route']['internal']);
    }

    #[Test]
    public function it_hides_credentials_in_headers(): void
    {
        $collector = new RequestCollector();

        $request = $this->request('GET', '/', [
            'Authorization' => 'Token secret-value',
            'Cookie' => 'flarum_session=abc',
            'Accept' => 'application/json',
        ]);

        $response = $this->response(200, ['Set-Cookie' => 'flarum_session=xyz', 'Content-Type' => 'application/json']);

        $data = $collector->collect($request, $response);

        $this->assertSame('••••••••', $data['requestHeaders']['Authorization']);
        $this->assertSame('••••••••', $data['requestHeaders']['Cookie']);
        $this->assertSame('application/json', $data['requestHeaders']['Accept']);
        $this->assertSame('••••••••', $data['responseHeaders']['Set-Cookie']);
    }

    #[Test]
    public function it_reports_json_api_parameters_only_when_there_are_some(): void
    {
        $collector = new RequestCollector();

        $this->assertSame([], $collector->collect($this->request(), $this->response())['jsonApi']);

        $withParams = new RequestCollector();
        $withParams->observe($this->request()->withQueryParams([
            'include' => 'user',
            'filter' => ['q' => 'hello'],
            'page' => ['limit' => 20],
            'unrelated' => 'x',
        ]));

        $jsonApi = $withParams->collect($this->request(), $this->response())['jsonApi'];

        $this->assertSame(['include', 'filter', 'page'], array_keys($jsonApi));
        $this->assertSame('user', $jsonApi['include']);
        $this->assertSame('{"q":"hello"}', $jsonApi['filter']);
    }

    #[Test]
    public function it_hides_credentials_in_the_query_string(): void
    {
        $collector = new RequestCollector();
        $collector->observe($this->request()->withQueryParams(['token' => 'secret', 'q' => 'hello']));

        $query = $collector->collect($this->request(), $this->response())['query'];

        $this->assertSame('••••••••', $query['token']);
        $this->assertSame('hello', $query['q']);
    }

    #[Test]
    public function it_names_the_collector_after_the_panel_it_fills(): void
    {
        $this->assertSame('request', (new RequestCollector())->name());
    }
}
