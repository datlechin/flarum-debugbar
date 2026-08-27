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

use Datlechin\FlarumDebugbar\Collector\TimelineCollector;
use Datlechin\FlarumDebugbar\Tests\unit\MakesHttpMessages;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class TimelineCollectorTest extends TestCase
{
    use MakesHttpMessages;

    private function collector(): TimelineCollector
    {
        return new TimelineCollector(microtime(true));
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function measures(TimelineCollector $collector): array
    {
        $data = $collector->collect($this->request(), $this->response());

        return array_column($data['measures'], null, 'name');
    }

    #[Test]
    public function it_records_nothing_until_asked_to(): void
    {
        $this->assertSame([], $this->collector()->collect($this->request(), $this->response())['measures']);
    }

    #[Test]
    public function it_times_a_span(): void
    {
        $collector = $this->collector();

        $collector->start('work', 'Some work');
        usleep(2000);
        $collector->stop('work');

        $measures = $this->measures($collector);

        $this->assertArrayHasKey('work', $measures);
        $this->assertSame('Some work', $measures['work']['label']);
        $this->assertGreaterThan(0.001, $measures['work']['duration']);
        $this->assertFalse($measures['work']['unfinished']);
    }

    #[Test]
    public function it_uses_the_name_as_the_label_when_no_label_is_given(): void
    {
        $collector = $this->collector();
        $collector->start('work');
        $collector->stop('work');

        $this->assertSame('work', $this->measures($collector)['work']['label']);
    }

    #[Test]
    public function it_returns_what_a_measured_callable_returned(): void
    {
        $this->assertSame('result', $this->collector()->measure('work', fn () => 'result'));
    }

    #[Test]
    public function it_closes_a_measured_span_even_when_the_callable_throws(): void
    {
        $collector = $this->collector();

        try {
            $collector->measure('work', function () {
                throw new \RuntimeException('nope');
            });
        } catch (\RuntimeException) {
        }

        $measures = $collector->collect($this->request(), $this->response())['measures'];

        // A span left open by an exception would be reported as unfinished and
        // stretched to the end of the request, hiding where the failure was.
        $this->assertCount(1, $measures);
        $this->assertFalse($measures[0]['unfinished']);
    }

    #[Test]
    public function it_marks_a_span_that_was_never_closed(): void
    {
        $collector = $this->collector();
        $collector->start('work');

        $measures = $collector->collect($this->request(), $this->response())['measures'];

        $this->assertTrue($measures[0]['unfinished']);
        $this->assertGreaterThanOrEqual(0, $measures[0]['duration']);
    }

    #[Test]
    public function it_keeps_both_spans_when_a_name_is_reused(): void
    {
        $collector = $this->collector();

        $collector->start('query', 'Query');
        $collector->stop('query');
        $collector->start('query', 'Query');
        $collector->stop('query');

        $this->assertCount(2, $collector->collect($this->request(), $this->response())['measures']);
    }

    #[Test]
    public function it_keeps_both_spans_when_a_name_is_reused_before_the_first_closes(): void
    {
        $collector = $this->collector();

        $collector->start('nested', 'Nested');
        $collector->start('nested', 'Nested');
        $collector->stop('nested');
        $collector->stop('nested');

        $measures = $collector->collect($this->request(), $this->response())['measures'];

        $this->assertCount(2, $measures);
        $this->assertSame([false, false], array_column($measures, 'unfinished'));
    }

    #[Test]
    public function stopping_something_that_was_never_started_does_nothing(): void
    {
        $collector = $this->collector();
        $collector->stop('never-started');

        $this->assertSame([], $collector->collect($this->request(), $this->response())['measures']);
    }

    #[Test]
    public function it_records_a_span_that_has_already_happened(): void
    {
        $start = microtime(true);
        $collector = new TimelineCollector($start);

        $collector->record('flarum.boot', 'Boot', $start, $start + 0.25);

        $measures = $this->measures($collector);

        $this->assertSame(0.0, $measures['flarum.boot']['start']);
        $this->assertEqualsWithDelta(0.25, $measures['flarum.boot']['duration'], 0.0001);
    }

    #[Test]
    public function spans_are_offsets_from_the_start_of_the_request(): void
    {
        // The frontend positions bars against the request's own duration, so a
        // span's start has to be relative to the request, not an absolute
        // clock reading.
        $start = microtime(true);
        $collector = new TimelineCollector($start);

        $collector->record('later', 'Later', $start + 0.1, $start + 0.2);

        $this->assertEqualsWithDelta(0.1, $this->measures($collector)['later']['start'], 0.0001);
    }

    #[Test]
    public function spans_are_reported_in_the_order_they_began(): void
    {
        $start = microtime(true);
        $collector = new TimelineCollector($start);

        $collector->record('third', 'Third', $start + 0.3, $start + 0.4);
        $collector->record('first', 'First', $start + 0.1, $start + 0.2);
        $collector->record('second', 'Second', $start + 0.2, $start + 0.3);

        $measures = $collector->collect($this->request(), $this->response())['measures'];

        $this->assertSame(['first', 'second', 'third'], array_column($measures, 'name'));
    }
}
