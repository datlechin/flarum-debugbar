<?php

namespace Datlechin\FlarumDebugbar\Tests\unit;

use Datlechin\FlarumDebugbar\Collector\QueryCollector;
use Illuminate\Database\Connection;
use Illuminate\Database\Events\QueryExecuted;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class QueryCollectorTest extends TestCase
{
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

        $connection = $this->createMock(Connection::class);
        $connection->method('getName')->willReturn('sqlite');

        $grammar = $this->createMock(\Illuminate\Database\Query\Grammars\Grammar::class);
        $grammar->method('substituteBindingsIntoRawSql')->willReturn("SELECT * FROM users WHERE id = '1'");

        $processor = $this->createMock(\Illuminate\Database\Query\Processors\Processor::class);

        $builder = $this->createMock(\Illuminate\Database\Query\Builder::class);
        $builder->method('getGrammar')->willReturn($grammar);

        $connection->method('query')->willReturn($builder);
        $connection->method('prepareBindings')->willReturn([1]);

        $event = new QueryExecuted('SELECT * FROM users WHERE id = ?', [1], 5.2, $connection);
        $collector->onQueryExecuted($event);

        $data = $collector->collect();

        $this->assertEquals(1, $data['nb_statements']);
        $this->assertEquals(5.2, $data['statements'][0]['duration']);
        $this->assertStringContainsString('SELECT', $data['statements'][0]['sql']);
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
}
