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
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;

/**
 * The payload is the only thing that switches the frontend on.
 *
 * The bar's JavaScript ships in every build, debug mode or not, and does
 * nothing at all unless it finds `app.data.debugbar`. So the rules about who
 * sees a debug bar are enforced here, and nowhere else.
 */
class AddsFrontendPayloadTest extends TestCase
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
     * The JSON Flarum hands to the frontend at boot.
     *
     * @return array<string, mixed>
     */
    protected function payload(ResponseInterface $response): array
    {
        $body = (string) $response->getBody();

        $this->assertMatchesRegularExpression(
            '/<script id="flarum-json-payload" type="application\/json">(.*?)<\/script>/s',
            $body,
            'the page carried no frontend payload'
        );

        preg_match('/<script id="flarum-json-payload" type="application\/json">(.*?)<\/script>/s', $body, $matches);

        return json_decode(html_entity_decode($matches[1], ENT_QUOTES), true) ?? [];
    }

    #[Test]
    public function an_administrator_gets_the_payload(): void
    {
        $response = $this->send($this->request('GET', '/', ['authenticatedAs' => 1]));
        $payload = $this->payload($response);

        $this->assertArrayHasKey('debugbar', $payload);
        $this->assertSame(['requestId', 'openByDefault'], array_keys($payload['debugbar']));

        // The id names the profile this very page is being written to, so the
        // frontend can fetch it a moment later.
        $this->assertSame($response->getHeaderLine(CollectProfile::HEADER), $payload['debugbar']['requestId']);
    }

    #[Test]
    public function an_ordinary_member_does_not(): void
    {
        $this->assertArrayNotHasKey('debugbar', $this->payload($this->send($this->request('GET', '/', ['authenticatedAs' => 2]))));
    }

    #[Test]
    public function a_guest_does_not(): void
    {
        $this->assertArrayNotHasKey('debugbar', $this->payload($this->send($this->request('GET', '/'))));
    }

    #[Test]
    public function nobody_does_outside_debug_mode(): void
    {
        $this->config('debug', false);

        $this->assertArrayNotHasKey('debugbar', $this->payload($this->send($this->request('GET', '/', ['authenticatedAs' => 1]))));
    }

    #[Test]
    public function the_admin_panel_gets_it_too(): void
    {
        $this->assertArrayHasKey('debugbar', $this->payload($this->send($this->request('GET', '/admin', ['authenticatedAs' => 1]))));
    }

    #[Test]
    public function the_forum_setting_reaches_the_frontend(): void
    {
        $this->setting('datlechin-debugbar.open_by_default', true);

        $payload = $this->payload($this->send($this->request('GET', '/', ['authenticatedAs' => 1])));

        $this->assertTrue($payload['debugbar']['openByDefault']);
    }
}
