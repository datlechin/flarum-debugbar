<?php

/*
 * This file is part of datlechin/flarum-debugbar.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\FlarumDebugbar\Collector;

use Illuminate\Contracts\Events\Dispatcher;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Every event dispatched during the request, in order.
 *
 * Two families are counted rather than listed. Eloquent's model events fire
 * several hundred times on a page that loads fifty discussions, and the
 * database's own events fire twice for every query — between them they bury
 * the one event anyone opened the panel to find. Neither is lost: the totals
 * are reported, and the Queries panel is the detailed view of the database
 * ones.
 */
class EventCollector implements CollectorInterface, SubscribesToEvents
{
    protected const LIMIT = 400;

    /**
     * Prefixes of the event families that are counted rather than listed.
     */
    protected const NOISE = [
        'eloquent.',
        'Illuminate\\Database\\Events\\',
    ];

    /**
     * @var list<array<string, mixed>>
     */
    protected array $events = [];

    /**
     * @var array<string, int>
     */
    protected array $collapsed = [];

    protected int $count = 0;

    protected int $dropped = 0;

    public function name(): string
    {
        return 'events';
    }

    public function subscribe(Dispatcher $events): void
    {
        $events->listen('*', $this->record(...));
    }

    /**
     * @param array<array-key, mixed> $payload
     */
    public function record(string $name, array $payload): void
    {
        $this->count++;

        if ($this->isNoise($name)) {
            $this->collapsed[$this->family($name)] = ($this->collapsed[$this->family($name)] ?? 0) + 1;

            return;
        }

        if (count($this->events) >= self::LIMIT) {
            $this->dropped++;

            return;
        }

        $this->events[] = [
            'name' => $name,
            'time' => microtime(true),
            'payload' => $this->describePayload($name, $payload),
        ];
    }

    /**
     * The types the event carried, minus the event itself.
     *
     * A class-based event is dispatched with the event object as its only
     * payload, so its type *is* the event's name — reporting it produced a
     * column that repeated the one beside it, word for word, on every row.
     *
     * @param array<array-key, mixed> $payload
     * @return list<string>
     */
    protected function describePayload(string $name, array $payload): array
    {
        $types = array_map(get_debug_type(...), $payload);

        return array_values(array_filter($types, fn (string $type) => $type !== $name));
    }

    public function collect(ServerRequestInterface $request, ResponseInterface $response): array
    {
        $collapsed = [];

        foreach ($this->collapsed as $name => $count) {
            $collapsed[] = ['name' => $name, 'count' => $count];
        }

        usort($collapsed, fn (array $a, array $b) => $b['count'] <=> $a['count']);

        return [
            'count' => $this->count,
            'dropped' => $this->dropped,
            'events' => $this->events,
            'collapsed' => $collapsed,
        ];
    }

    protected function isNoise(string $name): bool
    {
        foreach (self::NOISE as $prefix) {
            if (str_starts_with($name, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * `eloquent.retrieved: Flarum\User\User` and
     * `eloquent.retrieved: Flarum\Post\Post` are the same kind of noise, so
     * they are counted together. A class-based event is already its own
     * family.
     */
    protected function family(string $name): string
    {
        return str_contains($name, ':') ? strstr($name, ':', true).': *' : $name;
    }
}
