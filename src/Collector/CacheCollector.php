<?php

namespace Datlechin\FlarumDebugbar\Collector;

use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\Renderable;

class CacheCollector extends DataCollector implements Renderable
{
    protected array $operations = [];

    protected int $hits = 0;

    protected int $misses = 0;

    protected int $writes = 0;

    protected int $deletes = 0;

    public function recordHit(string $key, mixed $value): void
    {
        $this->hits++;
        $this->operations[] = [
            'type' => 'hit',
            'key' => $key,
            'time' => microtime(true),
        ];
    }

    public function recordMiss(string $key): void
    {
        $this->misses++;
        $this->operations[] = [
            'type' => 'miss',
            'key' => $key,
            'time' => microtime(true),
        ];
    }

    public function recordWrite(string $key): void
    {
        $this->writes++;
        $this->operations[] = [
            'type' => 'write',
            'key' => $key,
            'time' => microtime(true),
        ];
    }

    public function recordDelete(string $key): void
    {
        $this->deletes++;
        $this->operations[] = [
            'type' => 'delete',
            'key' => $key,
            'time' => microtime(true),
        ];
    }

    public function collect(): array
    {
        $messages = [];

        foreach ($this->operations as $op) {
            $label = match ($op['type']) {
                'hit' => 'info',
                'miss' => 'warning',
                'write' => 'debug',
                'delete' => 'error',
                default => 'info',
            };

            $prefix = strtoupper($op['type']);

            $messages[] = [
                'message' => "[{$prefix}] {$op['key']}",
                'message_html' => null,
                'is_string' => true,
                'label' => $label,
                'time' => $op['time'],
            ];
        }

        return [
            'count' => count($this->operations),
            'hits' => $this->hits,
            'misses' => $this->misses,
            'writes' => $this->writes,
            'deletes' => $this->deletes,
            'messages' => $messages,
        ];
    }

    public function getName(): string
    {
        return 'cache';
    }

    public function getWidgets(): array
    {
        return [
            'cache' => [
                'icon' => 'server-cog',
                'title' => 'Cache',
                'widget' => 'PhpDebugBar.Widgets.MessagesWidget',
                'map' => 'cache.messages',
                'default' => '[]',
            ],
            'cache:badge' => [
                'map' => 'cache.count',
                'default' => 0,
            ],
        ];
    }
}
