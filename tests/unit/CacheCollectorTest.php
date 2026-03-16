<?php

namespace Datlechin\FlarumDebugbar\Tests\unit;

use Datlechin\FlarumDebugbar\Collector\CacheCollector;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class CacheCollectorTest extends TestCase
{
    #[Test]
    public function it_collects_no_operations_by_default(): void
    {
        $collector = new CacheCollector();
        $data = $collector->collect();

        $this->assertEquals(0, $data['count']);
        $this->assertEquals(0, $data['hits']);
        $this->assertEquals(0, $data['misses']);
    }

    #[Test]
    public function it_records_cache_hits(): void
    {
        $collector = new CacheCollector();

        $collector->recordHit('users.1', ['name' => 'Admin']);

        $data = $collector->collect();

        $this->assertEquals(1, $data['hits']);
        $this->assertEquals(0, $data['misses']);
        $this->assertEquals('info', $data['messages'][0]['label']);
        $this->assertStringContainsString('HIT', $data['messages'][0]['message']);
    }

    #[Test]
    public function it_records_cache_misses(): void
    {
        $collector = new CacheCollector();

        $collector->recordMiss('users.999');

        $data = $collector->collect();

        $this->assertEquals(0, $data['hits']);
        $this->assertEquals(1, $data['misses']);
        $this->assertEquals('warning', $data['messages'][0]['label']);
    }

    #[Test]
    public function it_records_writes_and_deletes(): void
    {
        $collector = new CacheCollector();

        $collector->recordWrite('settings');
        $collector->recordDelete('old-key');

        $data = $collector->collect();

        $this->assertEquals(1, $data['writes']);
        $this->assertEquals(1, $data['deletes']);
        $this->assertEquals(2, $data['count']);
    }

    #[Test]
    public function it_returns_correct_name(): void
    {
        $this->assertEquals('cache', (new CacheCollector())->getName());
    }
}
