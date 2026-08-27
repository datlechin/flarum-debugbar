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

use Datlechin\FlarumDebugbar\Http\Middleware\CollectProfile;
use Datlechin\FlarumDebugbar\Storage\ProfileStorage;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * The endpoints the bar reads profiles back through.
 *
 * A profile holds settings, SQL, request headers and the actor's groups, so
 * who may read one is not a detail — and it is enforced by a route that only
 * exists when the extenders ran.
 */
class ProfileApiTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('datlechin-debugbar');
        $this->config('debug', true);

        $this->prepareDatabase([
            'users' => [$this->normalUser()],
        ]);
    }

    /**
     * Produce a profile to read back, and return its id.
     */
    protected function storedProfile(): string
    {
        $response = $this->send($this->request('GET', '/api/discussions'));

        return $response->getHeaderLine(CollectProfile::HEADER);
    }

    #[Test]
    public function an_administrator_can_list_profiles(): void
    {
        $this->storedProfile();

        $response = $this->send($this->request('GET', '/api/debugbar/profiles', ['authenticatedAs' => 1]));

        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode($response->getBody(), true);

        $this->assertNotEmpty($body['data']);
        $this->assertSame(['id', 'time', 'method', 'uri', 'status', 'duration', 'memory'], array_keys($body['data'][0]));
    }

    #[Test]
    public function an_administrator_can_read_one_profile(): void
    {
        $id = $this->storedProfile();

        $response = $this->send($this->request('GET', "/api/debugbar/profiles/{$id}", ['authenticatedAs' => 1]));

        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode($response->getBody(), true);

        $this->assertSame($id, $body['data']['id']);
        $this->assertArrayHasKey('queries', $body['data']['data']);
    }

    #[Test]
    public function an_administrator_can_clear_the_history(): void
    {
        $this->storedProfile();

        $response = $this->send($this->request('DELETE', '/api/debugbar/profiles', ['authenticatedAs' => 1]));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertGreaterThan(0, json_decode($response->getBody(), true)['data']['cleared']);
        $this->assertSame([], $this->app()->getContainer()->make(ProfileStorage::class)->recent(50));
    }

    #[Test]
    public function an_ordinary_member_may_not_read_profiles(): void
    {
        $id = $this->storedProfile();

        foreach ([
            ['GET', '/api/debugbar/profiles'],
            ['GET', "/api/debugbar/profiles/{$id}"],
            ['DELETE', '/api/debugbar/profiles'],
        ] as [$method, $path]) {
            $response = $this->send($this->request($method, $path, ['authenticatedAs' => 2]));

            $this->assertEquals(403, $response->getStatusCode(), "{$method} {$path}");
        }
    }

    #[Test]
    public function a_guest_may_not_read_profiles(): void
    {
        $id = $this->storedProfile();

        $this->assertEquals(403, $this->send($this->request('GET', '/api/debugbar/profiles'))->getStatusCode());
        $this->assertEquals(403, $this->send($this->request('GET', "/api/debugbar/profiles/{$id}"))->getStatusCode());
    }

    #[Test]
    public function a_profile_that_is_no_longer_stored_is_a_404(): void
    {
        $response = $this->send($this->request('GET', '/api/debugbar/profiles/0123456789abcdef', ['authenticatedAs' => 1]));

        $this->assertEquals(404, $response->getStatusCode());
    }

    #[Test]
    public function an_id_that_is_not_one_of_ours_never_reaches_the_filesystem(): void
    {
        // Ids build file paths and arrive from a URL, so anything not in the
        // exact shape the storage issues is refused.
        foreach (['..%2F..%2Fconfig', 'index', 'zzzzzzzzzzzzzzzz'] as $id) {
            $response = $this->send($this->request('GET', "/api/debugbar/profiles/{$id}", ['authenticatedAs' => 1]));

            $this->assertEquals(404, $response->getStatusCode(), $id);
        }
    }

    #[Test]
    public function reading_profiles_does_not_create_one(): void
    {
        // Without this, opening the bar would append a row to the list it had
        // just fetched, and every refresh would append another.
        $this->storedProfile();

        $storage = $this->app()->getContainer()->make(ProfileStorage::class);
        $before = count($storage->recent(50));

        $response = $this->send($this->request('GET', '/api/debugbar/profiles', ['authenticatedAs' => 1]));

        $this->assertFalse($response->hasHeader(CollectProfile::HEADER));
        $this->assertCount($before, $storage->recent(50));
    }

    #[Test]
    public function the_endpoints_do_not_exist_outside_debug_mode(): void
    {
        $this->config('debug', false);

        $response = $this->send($this->request('GET', '/api/debugbar/profiles', ['authenticatedAs' => 1]));

        $this->assertEquals(404, $response->getStatusCode());
    }
}
