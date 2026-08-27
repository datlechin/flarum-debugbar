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

use Datlechin\FlarumDebugbar\Collector\EventCollector;
use Datlechin\FlarumDebugbar\Tests\unit\MakesHttpMessages;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class EventCollectorTest extends TestCase
{
    use MakesHttpMessages;

    private function collect(EventCollector $collector): array
    {
        return $collector->collect($this->request(), $this->response());
    }

    #[Test]
    public function it_records_an_event_with_the_types_it_carried(): void
    {
        $collector = new EventCollector();
        $collector->record('Flarum\\Post\\Event\\Posted', [new \stdClass(), 'context', 42]);

        $data = $this->collect($collector);

        $this->assertSame(1, $data['count']);
        $this->assertSame('Flarum\\Post\\Event\\Posted', $data['events'][0]['name']);
        $this->assertSame(['stdClass', 'string', 'int'], $data['events'][0]['payload']);
    }

    #[Test]
    public function it_does_not_repeat_a_class_based_event_as_its_own_payload(): void
    {
        // Laravel dispatches a class-based event with the event object as its
        // only payload, so the type *is* the name. Reporting it produced a
        // column that repeated the one beside it, word for word, on every row.
        $collector = new EventCollector();
        $collector->record(\RuntimeException::class, [new \RuntimeException()]);

        $this->assertSame([], $this->collect($collector)['events'][0]['payload']);
    }

    #[Test]
    public function it_keeps_the_parts_of_a_payload_that_are_not_the_event(): void
    {
        $collector = new EventCollector();
        $collector->record(\RuntimeException::class, [new \RuntimeException(), 'extra']);

        $this->assertSame(['string'], $this->collect($collector)['events'][0]['payload']);
    }

    #[Test]
    public function it_counts_database_events_rather_than_listing_them(): void
    {
        // Two of these fire for every query in the request. Listed, they bury
        // everything else — and the Queries panel already shows what they were
        // doing, in far more detail.
        $collector = new EventCollector();

        foreach (range(1, 40) as $ignored) {
            $collector->record('Illuminate\\Database\\Events\\QueryExecuted', []);
            $collector->record('Illuminate\\Database\\Events\\StatementPrepared', []);
        }

        $collector->record('Flarum\\Post\\Event\\Posted', []);

        $data = $this->collect($collector);

        $this->assertSame(81, $data['count']);
        $this->assertSame(['Flarum\\Post\\Event\\Posted'], array_column($data['events'], 'name'));
        $this->assertSame(
            [
                ['name' => 'Illuminate\\Database\\Events\\QueryExecuted', 'count' => 40],
                ['name' => 'Illuminate\\Database\\Events\\StatementPrepared', 'count' => 40],
            ],
            $data['collapsed']
        );
    }

    #[Test]
    public function it_counts_eloquent_events_rather_than_listing_them(): void
    {
        // A page that loads fifty discussions fires several hundred of these.
        // Listed, they bury every event anyone actually came to look for.
        $collector = new EventCollector();

        foreach (range(1, 300) as $ignored) {
            $collector->record('eloquent.retrieved: Flarum\\Discussion\\Discussion', []);
        }

        $collector->record('Flarum\\Post\\Event\\Posted', []);

        $data = $this->collect($collector);

        $this->assertSame(301, $data['count']);
        $this->assertCount(1, $data['events']);
        $this->assertSame('Flarum\\Post\\Event\\Posted', $data['events'][0]['name']);
        $this->assertSame([['name' => 'eloquent.retrieved: *', 'count' => 300]], $data['collapsed']);
    }

    #[Test]
    public function it_counts_the_same_eloquent_event_for_different_models_together(): void
    {
        $collector = new EventCollector();
        $collector->record('eloquent.retrieved: Flarum\\User\\User', []);
        $collector->record('eloquent.retrieved: Flarum\\Post\\Post', []);
        $collector->record('eloquent.booted: Flarum\\User\\User', []);

        $data = $this->collect($collector);

        $this->assertSame(
            [
                ['name' => 'eloquent.retrieved: *', 'count' => 2],
                ['name' => 'eloquent.booted: *', 'count' => 1],
            ],
            $data['collapsed']
        );
    }

    #[Test]
    public function the_noisiest_families_are_listed_first(): void
    {
        $collector = new EventCollector();
        $collector->record('eloquent.booting: A', []);
        $collector->record('eloquent.retrieved: A', []);
        $collector->record('eloquent.retrieved: B', []);

        $this->assertSame(['eloquent.retrieved: *', 'eloquent.booting: *'], array_column($this->collect($collector)['collapsed'], 'name'));
    }

    #[Test]
    public function it_stops_listing_events_before_it_fills_the_disk(): void
    {
        $collector = new EventCollector();

        foreach (range(1, 450) as $n) {
            $collector->record("acme.event.{$n}", []);
        }

        $data = $this->collect($collector);

        $this->assertCount(400, $data['events']);
        $this->assertSame(50, $data['dropped']);
        $this->assertSame(450, $data['count']);
    }

    #[Test]
    public function it_records_nothing_when_nothing_happened(): void
    {
        $data = $this->collect(new EventCollector());

        $this->assertSame(0, $data['count']);
        $this->assertSame([], $data['events']);
        $this->assertSame([], $data['collapsed']);
    }
}
