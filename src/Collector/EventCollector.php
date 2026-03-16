<?php

namespace Datlechin\FlarumDebugbar\Collector;

use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\Renderable;

class EventCollector extends DataCollector implements Renderable
{
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
        $messages = [];

        foreach ($this->events as $event) {
            $label = 'info';

            // Highlight interesting event types
            if (str_contains($event['event'], 'Exception') || str_contains($event['event'], 'Error')) {
                $label = 'error';
            } elseif (str_contains($event['event'], 'eloquent')) {
                $label = 'debug';
            }

            $messages[] = [
                'message' => $event['event'],
                'message_html' => null,
                'is_string' => true,
                'label' => $label,
                'time' => $event['time'],
            ];
        }

        return [
            'count' => count($this->events),
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
