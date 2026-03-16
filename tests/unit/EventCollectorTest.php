<?php

namespace Datlechin\FlarumDebugbar\Tests\unit;

use Datlechin\FlarumDebugbar\Collector\EventCollector;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class EventCollectorTest extends TestCase
{
    #[Test]
    public function it_collects_no_events_by_default(): void
    {
        $collector = new EventCollector();
        $data = $collector->collect();

        $this->assertEquals(0, $data['count']);
        $this->assertEmpty($data['messages']);
    }

    #[Test]
    public function it_records_wildcard_events(): void
    {
        $collector = new EventCollector();

        $collector->onWildcardEvent('Flarum\\Post\\Event\\Posted', []);
        $collector->onWildcardEvent('Flarum\\Discussion\\Event\\Started', []);

        $data = $collector->collect();

        $this->assertEquals(2, $data['count']);
        $this->assertCount(2, $data['messages']);
        $this->assertEquals('Flarum\\Post\\Event\\Posted', $data['messages'][0]['message']);
    }

    #[Test]
    public function it_labels_error_events(): void
    {
        $collector = new EventCollector();

        $collector->onWildcardEvent('SomeException', []);

        $data = $collector->collect();

        $this->assertEquals('error', $data['messages'][0]['label']);
    }

    #[Test]
    public function it_labels_eloquent_events_as_debug(): void
    {
        $collector = new EventCollector();

        $collector->onWildcardEvent('eloquent.created: App\\Models\\User', []);

        $data = $collector->collect();

        $this->assertEquals('debug', $data['messages'][0]['label']);
    }

    #[Test]
    public function it_returns_correct_name(): void
    {
        $this->assertEquals('events', (new EventCollector())->getName());
    }
}
