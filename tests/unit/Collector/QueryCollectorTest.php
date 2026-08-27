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

use Datlechin\FlarumDebugbar\Collector\QueryCollector;
use Datlechin\FlarumDebugbar\Tests\unit\MakesHttpMessages;
use Datlechin\FlarumDebugbar\Tests\unit\MakesPaths;
use Illuminate\Database\Connection;
use Illuminate\Database\Events\QueryExecuted;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class QueryCollectorTest extends TestCase
{
    use MakesHttpMessages;
    use MakesPaths;

    private function collector(bool $traceOrigin = false): QueryCollector
    {
        return new QueryCollector($this->paths(), $traceOrigin);
    }

    private function event(string $sql, array $bindings = [], float $milliseconds = 1.0, string $connection = 'flarum'): QueryExecuted
    {
        // A stub rather than a mock: nothing here asserts how the connection
        // was used, only that its name comes through.
        $stub = $this->createStub(Connection::class);
        $stub->method('getName')->willReturn($connection);

        return new QueryExecuted($sql, $bindings, $milliseconds, $stub);
    }

    private function collect(QueryCollector $collector): array
    {
        return $collector->collect($this->request(), $this->response());
    }

    #[Test]
    public function it_starts_with_nothing(): void
    {
        $data = $this->collect($this->collector());

        $this->assertSame(0, $data['count']);
        $this->assertSame(0.0, $data['duration']);
        $this->assertSame([], $data['queries']);
    }

    #[Test]
    public function it_records_a_statement_with_its_parameters(): void
    {
        $collector = $this->collector();
        $collector->record($this->event('select * from `users` where `id` = ?', [7], 2.5));

        $query = $this->collect($collector)['queries'][0];

        $this->assertSame('select * from `users` where `id` = ?', $query['sql']);
        $this->assertSame(['7'], $query['bindings']);
        $this->assertSame('flarum', $query['connection']);
    }

    #[Test]
    public function it_reports_durations_in_seconds(): void
    {
        // The event carries milliseconds; everything else in a profile is in
        // seconds, and a panel that mixed the two would be unreadable.
        $collector = $this->collector();
        $collector->record($this->event('select 1', [], 2.5));
        $collector->record($this->event('select 2', [], 7.5));

        $data = $this->collect($collector);

        $this->assertSame(0.0025, $data['queries'][0]['duration']);
        $this->assertEqualsWithDelta(0.01, $data['duration'], 0.000001);
    }

    #[Test]
    public function it_interpolates_parameters_for_copying_into_a_client(): void
    {
        $collector = $this->collector();
        $collector->record($this->event('select * from `t` where `a` = ? and `b` = ?', ['hello', 42]));

        $this->assertSame(
            "select * from `t` where `a` = 'hello' and `b` = 42",
            $this->collect($collector)['queries'][0]['preview']
        );
    }

    #[Test]
    public function it_escapes_quotes_when_interpolating(): void
    {
        $collector = $this->collector();
        $collector->record($this->event('select * from `t` where `a` = ?', ["O'Brien"]));

        $this->assertSame(
            "select * from `t` where `a` = 'O''Brien'",
            $this->collect($collector)['queries'][0]['preview']
        );
    }

    #[Test]
    public function it_leaves_the_statement_alone_when_the_parameters_do_not_line_up(): void
    {
        // A statement whose `?` count does not match its bindings — because it
        // contains a literal question mark, or uses named parameters — cannot
        // be interpolated positionally without producing something wrong.
        $collector = $this->collector();
        $collector->record($this->event("select * from `t` where `a` = ? and `b` = 'why?'", ['x', 'y']));

        $query = $this->collect($collector)['queries'][0];

        $this->assertSame($query['sql'], $query['preview']);
    }

    #[Test]
    public function it_counts_a_statement_repeated_with_the_same_parameters(): void
    {
        $collector = $this->collector();
        $collector->record($this->event('select * from `users` where `id` = ?', [7]));
        $collector->record($this->event('select * from `users` where `id` = ?', [7]));
        $collector->record($this->event('select * from `users` where `id` = ?', [7]));

        $data = $this->collect($collector);

        $this->assertSame(3, $data['duplicates']);
        $this->assertSame([3, 3, 3], array_column($data['queries'], 'occurrences'));
    }

    #[Test]
    public function it_does_not_count_the_same_statement_with_different_parameters(): void
    {
        // A loop over ids is the ordinary shape of a page, not a fault.
        // Flagging it would bury the repeats that really were avoidable.
        $collector = $this->collector();
        $collector->record($this->event('select * from `users` where `id` = ?', [1]));
        $collector->record($this->event('select * from `users` where `id` = ?', [2]));

        $data = $this->collect($collector);

        $this->assertSame(0, $data['duplicates']);
        $this->assertSame([1, 1], array_column($data['queries'], 'occurrences'));
    }

    #[Test]
    public function it_does_not_count_the_same_statement_on_different_connections(): void
    {
        $collector = $this->collector();
        $collector->record($this->event('select 1', [], 1.0, 'flarum'));
        $collector->record($this->event('select 1', [], 1.0, 'reporting'));

        $this->assertSame(0, $this->collect($collector)['duplicates']);
    }

    #[Test]
    public function it_stops_recording_statements_long_before_it_fills_the_disk(): void
    {
        $collector = $this->collector();

        for ($i = 0; $i < 1100; $i++) {
            $collector->record($this->event("select {$i}", [], 1.0));
        }

        $data = $this->collect($collector);

        $this->assertCount(1000, $data['queries']);
        $this->assertSame(100, $data['dropped']);

        // The total is still the true total, and so is the accumulated time:
        // a runaway loop is exactly when the real figure matters.
        $this->assertSame(1100, $data['count']);
        $this->assertEqualsWithDelta(1.1, $data['duration'], 0.0001);
    }

    #[Test]
    public function it_truncates_a_very_long_parameter(): void
    {
        $collector = $this->collector();
        $collector->record($this->event('insert into `t` values (?)', [str_repeat('x', 5000)]));

        $this->assertLessThan(200, strlen($this->collect($collector)['queries'][0]['bindings'][0]));
    }

    #[Test]
    public function it_survives_a_parameter_that_is_not_text(): void
    {
        $collector = $this->collector();
        $collector->record($this->event('insert into `t` values (?, ?, ?)', [
            new \DateTimeImmutable('2026-01-02 03:04:05'),
            null,
            true,
        ]));

        $this->assertSame(
            ['2026-01-02 03:04:05', 'null', 'true'],
            $this->collect($collector)['queries'][0]['bindings']
        );
    }

    #[Test]
    public function it_records_no_origin_when_tracing_is_switched_off(): void
    {
        $collector = $this->collector(traceOrigin: false);
        $collector->record($this->event('select 1'));

        $this->assertNull($this->collect($collector)['queries'][0]['origin']);
    }

    #[Test]
    public function it_never_blames_its_own_files_for_a_query(): void
    {
        // The frame that runs `record()` is inside this extension. Reporting
        // it would mean every query in the forum appeared to come from the
        // debug bar, which is what happened when the exclusion was matched
        // against a fragment of the Composer install path.
        $collector = $this->collector(traceOrigin: true);
        $collector->record($this->event('select 1'));

        $origin = $this->collect($collector)['queries'][0]['origin'];

        $this->assertNotNull($origin);
        $this->assertStringNotContainsString('src/Collector', $origin);
        $this->assertStringContainsString('QueryCollectorTest.php', $origin);
    }
}
