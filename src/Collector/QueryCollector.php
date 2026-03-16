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
            'sql' => $event->sql,
            'params' => new \stdClass(),
            'duration' => $event->time / 1000,
            'duration_str' => $this->getDataFormatter()->formatDuration($event->time / 1000),
            'connection' => $event->connectionName,
            'is_success' => true,
        ];
    }

    public function collect(): array
    {
        $totalTime = 0;

        // Pass 1: build frequency map of sql@connection
        $templateFrequency = [];

        foreach ($this->queries as $query) {
            $totalTime += $query['duration'];
            $key = $query['sql'] . '@' . $query['connection'];
            $templateFrequency[$key] = ($templateFrequency[$key] ?? 0) + 1;
        }

        // Pass 2: stamp duplicate_count on each statement
        $nbDuplicateStatements = 0;

        foreach ($this->queries as &$query) {
            $key = $query['sql'] . '@' . $query['connection'];
            $count = $templateFrequency[$key];

            if ($count > 1) {
                $query['is_duplicate'] = true;
                $nbDuplicateStatements++;
            }
        }

        unset($query);

        return [
            'nb_statements' => count($this->queries),
            'nb_failed_statements' => 0,
            'nb_duplicate_statements' => $nbDuplicateStatements,
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
