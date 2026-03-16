<?php

namespace Datlechin\FlarumDebugbar\Tests\unit;

use Datlechin\FlarumDebugbar\Collector\QueryCollector;
use Illuminate\Database\Connection;
use Illuminate\Database\Events\QueryExecuted;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class QueryCollectorTest extends TestCase
{
    private function makeEvent(string $sql, array $bindings, float $time, string $connectionName = 'sqlite'): QueryExecuted
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getName')->willReturn($connectionName);

        return new QueryExecuted($sql, $bindings, $time, $connection);
    }

    #[Test]
    public function it_collects_no_queries_by_default(): void
    {
        $collector = new QueryCollector();
        $data = $collector->collect();

        $this->assertEquals(0, $data['nb_statements']);
        $this->assertEmpty($data['statements']);
    }

    #[Test]
    public function it_records_query_executed_events(): void
    {
        $collector = new QueryCollector();

        $event = $this->makeEvent('SELECT * FROM users WHERE id = ?', [1], 5.2);
        $collector->onQueryExecuted($event);

        $data = $collector->collect();

        $this->assertEquals(1, $data['nb_statements']);
        $this->assertEquals(0.0052, $data['statements'][0]['duration']);
        $this->assertEquals('SELECT * FROM users WHERE id = ?', $data['statements'][0]['sql']);
    }

    #[Test]
    public function it_returns_correct_name(): void
    {
        $collector = new QueryCollector();

        $this->assertEquals('queries', $collector->getName());
    }

    #[Test]
    public function it_provides_widgets(): void
    {
        $collector = new QueryCollector();
        $widgets = $collector->getWidgets();

        $this->assertArrayHasKey('queries', $widgets);
        $this->assertArrayHasKey('queries:badge', $widgets);
        $this->assertEquals('database', $widgets['queries']['icon']);
    }

    #[Test]
    public function it_detects_duplicate_templates_with_different_bindings(): void
    {
        $collector = new QueryCollector();

        $collector->onQueryExecuted($this->makeEvent('SELECT * FROM users WHERE id = ?', [1], 1.0));
        $collector->onQueryExecuted($this->makeEvent('SELECT * FROM users WHERE id = ?', [2], 1.0));
        $collector->onQueryExecuted($this->makeEvent('SELECT * FROM users WHERE id = ?', [3], 1.0));

        $data = $collector->collect();

        $this->assertEquals(3, $data['nb_duplicate_statements']);
    }

    #[Test]
    public function it_detects_no_duplicates_for_unique_queries(): void
    {
        $collector = new QueryCollector();

        $collector->onQueryExecuted($this->makeEvent('SELECT * FROM users WHERE id = ?', [1], 1.0));
        $collector->onQueryExecuted($this->makeEvent('SELECT * FROM posts WHERE id = ?', [1], 1.0));

        $data = $collector->collect();

        $this->assertEquals(0, $data['nb_duplicate_statements']);
    }

    #[Test]
    public function it_scopes_duplicate_detection_per_connection(): void
    {
        $collector = new QueryCollector();

        $collector->onQueryExecuted($this->makeEvent('SELECT * FROM users WHERE id = ?', [1], 1.0, 'sqlite'));
        $collector->onQueryExecuted($this->makeEvent('SELECT * FROM users WHERE id = ?', [2], 1.0, 'mysql'));

        $data = $collector->collect();

        $this->assertEquals(0, $data['nb_duplicate_statements']);
    }
}
