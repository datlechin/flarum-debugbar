<?php

/*
 * This file is part of datlechin/flarum-debugbar.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\FlarumDebugbar\Tests\unit;

use Datlechin\FlarumDebugbar\Collector\MessageCollector;
use Datlechin\FlarumDebugbar\Collector\TimelineCollector;
use Datlechin\FlarumDebugbar\Debugbar;
use Datlechin\FlarumDebugbar\Profile;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DebugbarTest extends TestCase
{
    use MakesHttpMessages;
    use MakesPaths;

    private function enabled(): Debugbar
    {
        $debugbar = new Debugbar(enabled: true, startedAt: microtime(true));
        $debugbar->addCollector(new TimelineCollector($debugbar->startedAt()));
        $debugbar->addCollector(new MessageCollector($this->paths()));

        return $debugbar;
    }

    #[Test]
    public function it_issues_an_id_that_can_be_used_as_a_filename(): void
    {
        $this->assertMatchesRegularExpression('/^[a-f0-9]{16}$/', (new Debugbar())->id());
    }

    #[Test]
    public function every_bar_gets_its_own_id(): void
    {
        $this->assertNotSame((new Debugbar())->id(), (new Debugbar())->id());
    }

    #[Test]
    public function it_collects_every_collector_under_its_own_name(): void
    {
        $profile = $this->enabled()->collect($this->request('POST', '/api/posts'), $this->response(201));

        $this->assertInstanceOf(Profile::class, $profile);
        $this->assertSame(['timeline', 'messages'], array_keys($profile->data));
        $this->assertSame('POST', $profile->method);
        $this->assertSame(201, $profile->status);
        $this->assertGreaterThan(0, $profile->memory);
    }

    #[Test]
    public function it_records_the_path_and_query_but_not_the_origin(): void
    {
        // Every profile in the list shares an origin, so it is all noise in a
        // bar a few hundred pixels wide.
        $request = $this->request('GET', 'https://forum.example.com/d/1-hello?page=2');

        $this->assertSame('/d/1-hello?page=2', $this->enabled()->collect($request, $this->response())->uri);
    }

    #[Test]
    public function a_disabled_bar_collects_nothing(): void
    {
        $debugbar = new Debugbar(enabled: false);
        $debugbar->addCollector(new MessageCollector($this->paths()));

        $this->assertFalse($debugbar->isEnabled());
        $this->assertNull($debugbar->collect($this->request(), $this->response()));
    }

    #[Test]
    public function a_disabled_bar_still_accepts_calls_from_extension_code(): void
    {
        // The whole point of binding it unconditionally: code that logs to the
        // debug bar should not have to check whether there is one.
        $debugbar = new Debugbar(enabled: false);

        $debugbar->info('hello');
        $debugbar->warning('careful');
        $debugbar->error('broken');
        $debugbar->debug('detail');
        $debugbar->exception(new \RuntimeException('nope'));
        $debugbar->startMeasure('work');
        $debugbar->stopMeasure('work');
        $debugbar->recordMeasure('boot', 'Boot', 0.0, 1.0);

        $this->assertSame('result', $debugbar->measure('work', fn () => 'result'));
        $this->assertNull($debugbar->collect($this->request(), $this->response()));
    }

    #[Test]
    public function a_discarded_request_produces_no_profile(): void
    {
        // Reading profiles must not write one, or opening the bar would append
        // a row to the list it had just fetched.
        $debugbar = $this->enabled();
        $debugbar->discard();

        $this->assertTrue($debugbar->isDiscarded());
        $this->assertNull($debugbar->collect($this->request(), $this->response()));
    }

    #[Test]
    public function logging_reaches_the_message_collector(): void
    {
        $debugbar = $this->enabled();

        $debugbar->info('one');
        $debugbar->warning('two');
        $debugbar->error('three');
        $debugbar->debug('four');
        $debugbar->log('five', MessageCollector::INFO);
        $debugbar->exception(new \RuntimeException('six'));

        $messages = $debugbar->collect($this->request(), $this->response())->data['messages']['messages'];

        $this->assertSame(
            ['info', 'warning', 'error', 'debug', 'info', 'error'],
            array_column($messages, 'level')
        );
    }

    #[Test]
    public function measuring_reaches_the_timeline_collector(): void
    {
        $debugbar = $this->enabled();

        $debugbar->startMeasure('work', 'Some work');
        $debugbar->stopMeasure('work');

        $this->assertSame('Some work', $debugbar->collect($this->request(), $this->response())->data['timeline']['measures'][0]['label']);
    }

    #[Test]
    public function it_finds_a_collector_by_name_or_by_class(): void
    {
        $debugbar = $this->enabled();

        $this->assertInstanceOf(MessageCollector::class, $debugbar->collector('messages'));
        $this->assertInstanceOf(MessageCollector::class, $debugbar->collector(MessageCollector::class));
        $this->assertNull($debugbar->collector('nothing-like-this'));
    }

    #[Test]
    public function a_later_collector_replaces_an_earlier_one_of_the_same_name(): void
    {
        $debugbar = new Debugbar();
        $first = new FakeCollector('widgets', ['from' => 'first']);
        $second = new FakeCollector('widgets', ['from' => 'second']);

        $debugbar->addCollector($first);
        $debugbar->addCollector($second);

        $this->assertSame($second, $debugbar->collector('widgets'));
        $this->assertSame(['from' => 'second'], $debugbar->collect($this->request(), $this->response())->data['widgets']);
    }

    #[Test]
    public function a_bar_with_no_timeline_still_measures_without_complaint(): void
    {
        // Every collector can be switched off individually, so nothing may
        // assume any particular one is present.
        $debugbar = new Debugbar();

        $this->assertSame('result', $debugbar->measure('work', fn () => 'result'));

        $debugbar->info('nowhere to go');

        $this->assertSame([], $debugbar->collect($this->request(), $this->response())->data);
    }
}

