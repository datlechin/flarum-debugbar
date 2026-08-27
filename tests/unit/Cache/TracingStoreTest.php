<?php

/*
 * This file is part of datlechin/flarum-debugbar.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\FlarumDebugbar\Tests\unit\Cache;

use Datlechin\FlarumDebugbar\Cache\TracingLockableStore;
use Datlechin\FlarumDebugbar\Cache\TracingStore;
use Datlechin\FlarumDebugbar\Collector\CacheCollector;
use Datlechin\FlarumDebugbar\Tests\unit\MakesHttpMessages;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Cache\LockProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class TracingStoreTest extends TestCase
{
    use MakesHttpMessages;

    private CacheCollector $collector;

    private Repository $cache;

    protected function setUp(): void
    {
        $this->collector = new CacheCollector();
        $this->cache = new Repository(new TracingStore(new ArrayStore(), $this->collector));
    }

    /**
     * @return array<string, mixed>
     */
    private function collect(): array
    {
        return $this->collector->collect($this->request(), $this->response());
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    private function operations(): array
    {
        return array_map(
            fn (array $operation) => [$operation['type'], $operation['key']],
            $this->collect()['operations']
        );
    }

    #[Test]
    public function it_reports_a_miss_and_then_a_hit(): void
    {
        $this->cache->get('greeting');
        $this->cache->put('greeting', 'hello', 60);
        $this->cache->get('greeting');

        $this->assertSame([['miss', 'greeting'], ['write', 'greeting'], ['hit', 'greeting']], $this->operations());
    }

    #[Test]
    public function it_counts_an_existence_check_once(): void
    {
        // `Repository::has()` is implemented in terms of `get()`. A decorator
        // placed on the repository rather than the store sees both calls and
        // reports two reads for one question.
        $this->cache->has('greeting');

        $this->assertSame([['miss', 'greeting']], $this->operations());
        $this->assertSame(1, $this->collect()['totals'][CacheCollector::MISS]);
    }

    #[Test]
    public function it_sees_the_operations_a_repository_delegates_straight_to_the_store(): void
    {
        // None of these pass through `Repository::get()` or `put()`, so a
        // repository-level decorator misses every one of them.
        $this->cache->forever('permanent', 'value');
        $this->cache->increment('counter');
        $this->cache->decrement('counter');

        $this->assertSame(
            [['write', 'permanent'], ['write', 'counter'], ['write', 'counter']],
            $this->operations()
        );
    }

    #[Test]
    public function it_reports_reads_and_writes_through_remember(): void
    {
        $this->cache->remember('computed', 60, fn () => 'value');
        $this->cache->remember('computed', 60, fn () => 'value');

        $this->assertSame(
            [['miss', 'computed'], ['write', 'computed'], ['hit', 'computed']],
            $this->operations()
        );
    }

    #[Test]
    public function it_reports_bulk_reads_and_writes(): void
    {
        $this->cache->putMany(['a' => 1, 'b' => 2], 60);
        $this->cache->many(['a', 'b', 'c']);

        $this->assertSame(
            [['write', 'a'], ['write', 'b'], ['hit', 'a'], ['hit', 'b'], ['miss', 'c']],
            $this->operations()
        );
    }

    #[Test]
    public function it_reports_deletions_and_flushes(): void
    {
        $this->cache->forget('a');
        $this->cache->clear();

        $this->assertSame([['forget', 'a'], ['flush', '*']], $this->operations());
    }

    #[Test]
    public function it_returns_exactly_what_the_underlying_store_returns(): void
    {
        $this->cache->put('greeting', 'hello', 60);

        $this->assertSame('hello', $this->cache->get('greeting'));
        $this->assertSame('fallback', $this->cache->get('missing', 'fallback'));
        $this->assertTrue($this->cache->has('greeting'));
        $this->assertFalse($this->cache->has('missing'));
        $this->assertSame(['greeting' => 'hello', 'missing' => null], $this->cache->many(['greeting', 'missing']));
    }

    #[Test]
    public function it_reports_the_hit_rate(): void
    {
        $this->cache->put('a', 1, 60);
        $this->cache->get('a');
        $this->cache->get('a');
        $this->cache->get('b');

        $this->assertEqualsWithDelta(2 / 3, $this->collect()['hitRate'], 0.0001);
    }

    #[Test]
    public function the_hit_rate_is_unknown_rather_than_zero_when_nothing_was_read(): void
    {
        // Nought per cent reads as a broken cache; "not applicable" reads as
        // what it is.
        $this->assertNull($this->collect()['hitRate']);
    }

    #[Test]
    public function it_keeps_atomic_locking_available(): void
    {
        // Core asks the *store* whether locks are available and quietly falls
        // back to a non-atomic path when the answer is no. A decorator that
        // dropped the interface would change how the forum behaves whenever
        // the debug bar was switched on.
        $store = new TracingLockableStore(new ArrayStore(), $this->collector);

        $this->assertInstanceOf(LockProvider::class, $store);
        $this->assertTrue($store->lock('job', 10)->get());
    }

    #[Test]
    public function it_exposes_the_store_it_wraps(): void
    {
        // So the environment panel can report the real driver rather than the
        // wrapper the debug bar put in front of it.
        $inner = new ArrayStore();

        $this->assertSame($inner, (new TracingStore($inner, $this->collector))->inner());
    }

    #[Test]
    public function it_stops_recording_operations_before_it_fills_the_disk(): void
    {
        for ($i = 0; $i < 550; $i++) {
            $this->cache->get("key.{$i}");
        }

        $data = $this->collect();

        $this->assertCount(500, $data['operations']);
        $this->assertSame(50, $data['dropped']);
        $this->assertSame(550, $data['totals'][CacheCollector::MISS]);
    }
}
