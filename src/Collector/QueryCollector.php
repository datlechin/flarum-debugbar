<?php

namespace Datlechin\FlarumDebugbar\Collector;

use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\Renderable;
use Illuminate\Database\Events\QueryExecuted;

class QueryCollector extends DataCollector implements Renderable
{
    protected array $queries = [];

    public function onQueryExecuted(QueryExecuted $event): void
    {
        $this->queries[] = [
            'sql' => $event->toRawSql(),
            'duration' => $event->time / 1000,
            'duration_str' => $this->getDataFormatter()->formatDuration($event->time / 1000),
            'connection' => $event->connectionName,
            'is_success' => true,
        ];
    }

    public function collect(): array
    {
        $totalTime = 0;

        foreach ($this->queries as $query) {
            $totalTime += $query['duration'];
        }

        return [
            'nb_statements' => count($this->queries),
            'nb_failed_statements' => 0,
            'accumulated_duration' => $totalTime,
            'accumulated_duration_str' => $this->getDataFormatter()->formatDuration($totalTime),
            'statements' => $this->queries,
        ];
    }

    public function getName(): string
    {
        return 'queries';
    }

    public function getWidgets(): array
    {
        return [
            'queries' => [
                'icon' => 'database',
                'title' => 'Queries',
                'widget' => 'PhpDebugBar.Widgets.SQLQueriesWidget',
                'map' => 'queries',
                'default' => '[]',
            ],
            'queries:badge' => [
                'map' => 'queries.nb_statements',
                'default' => 0,
            ],
        ];
    }
}
