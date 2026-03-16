<?php

namespace Datlechin\FlarumDebugbar\Collector;

use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\Renderable;

class EventCollector extends DataCollector implements Renderable
{
    protected const MAX_ENTRIES = 500;

    protected array $events = [];

    public function onWildcardEvent(string $eventName, array $payload): void
    {
        $this->events[] = [
            'event' => $eventName,
            'time' => microtime(true),
            'payload_count' => count($payload),
        ];
    }

    public function collect(): array
    {
        $totalCount = count($this->events);
        $events = $this->events;
        $truncated = 0;

        if ($totalCount > self::MAX_ENTRIES) {
            $truncated = $totalCount - self::MAX_ENTRIES;
            $events = array_slice($events, 0, self::MAX_ENTRIES);
        }

        // Collapse eloquent events into a single summary entry
        $eloquentCount = 0;
        $filtered = [];

        foreach ($events as $event) {
            if (str_contains($event['event'], 'eloquent.')) {
                $eloquentCount++;
            } else {
                $filtered[] = $event;
            }
        }

        $messages = [];

        if ($eloquentCount > 0) {
            $messages[] = [
                'message' => "eloquent.* ({$eloquentCount} events collapsed)",
                'message_html' => null,
                'is_string' => true,
                'label' => 'debug',
                'time' => $events[0]['time'],
            ];
        }

        foreach ($filtered as $event) {
            $label = 'info';

            if (str_contains($event['event'], 'Exception') || str_contains($event['event'], 'Error')) {
                $label = 'error';
            }

            $messages[] = [
                'message' => $event['event'],
                'message_html' => null,
                'is_string' => true,
                'label' => $label,
                'time' => $event['time'],
            ];
        }

        if ($truncated > 0) {
            $messages[] = [
                'message' => "... {$truncated} more events truncated (cap: " . self::MAX_ENTRIES . ")",
                'message_html' => null,
                'is_string' => true,
                'label' => 'warning',
                'time' => microtime(true),
            ];
        }

        return [
            'count' => $totalCount,
            'messages' => $messages,
        ];
    }

    public function getName(): string
    {
        return 'events';
    }

    public function getWidgets(): array
    {
        return [
            'events' => [
                'icon' => 'bolt',
                'title' => 'Events',
                'widget' => 'PhpDebugBar.Widgets.MessagesWidget',
                'map' => 'events.messages',
                'default' => '[]',
            ],
            'events:badge' => [
                'map' => 'events.count',
                'default' => 0,
            ],
        ];
    }
}
