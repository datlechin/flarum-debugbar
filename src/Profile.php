<?php

/*
 * This file is part of datlechin/flarum-debugbar.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\FlarumDebugbar;

/**
 * Everything the collectors saw during one request.
 *
 * A profile is written to storage as the response leaves and read back by the
 * frontend a moment later, so it is a plain, immutable, JSON-round-trippable
 * value: no services, no closures, nothing that has to be re-resolved to make
 * sense of it.
 */
final readonly class Profile
{
    /**
     * @param array<string, mixed> $data Collector name => that collector's data.
     */
    public function __construct(
        public string $id,
        public float $time,
        public string $method,
        public string $uri,
        public int $status,
        public float $duration,
        public int $memory,
        public array $data,
    ) {
    }

    /**
     * The row shown in the request picker: enough to recognise a request by,
     * without reading every collector's data off disk.
     *
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        return [
            'id' => $this->id,
            'time' => $this->time,
            'method' => $this->method,
            'uri' => $this->uri,
            'status' => $this->status,
            'duration' => $this->duration,
            'memory' => $this->memory,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->summary() + ['data' => $this->data];
    }

    /**
     * @param array<string, mixed> $raw
     */
    public static function fromArray(array $raw): self
    {
        return new self(
            id: (string) ($raw['id'] ?? ''),
            time: (float) ($raw['time'] ?? 0),
            method: (string) ($raw['method'] ?? 'GET'),
            uri: (string) ($raw['uri'] ?? ''),
            status: (int) ($raw['status'] ?? 0),
            duration: (float) ($raw['duration'] ?? 0),
            memory: (int) ($raw['memory'] ?? 0),
            data: is_array($raw['data'] ?? null) ? $raw['data'] : [],
        );
    }
}
