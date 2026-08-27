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

use Datlechin\FlarumDebugbar\Support\SourcePath;
use Datlechin\FlarumDebugbar\Support\Values;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Messages logged from application code, and exceptions that reached us.
 *
 * This is the collector extension developers actually talk to, through
 * {@see \Datlechin\FlarumDebugbar\Debugbar::info()} and friends.
 */
class MessageCollector implements CollectorInterface
{
    public const DEBUG = 'debug';
    public const INFO = 'info';
    public const WARNING = 'warning';
    public const ERROR = 'error';

    /**
     * A runaway loop that logs on every iteration should not be able to fill
     * the disk, so we keep the first N and count the rest.
     */
    protected const LIMIT = 500;

    /**
     * @var list<array<string, mixed>>
     */
    protected array $messages = [];

    protected int $dropped = 0;

    public function __construct(
        protected SourcePath $paths,
    ) {
    }

    public function name(): string
    {
        return 'messages';
    }

    public function add(string $message, string $level = self::INFO): void
    {
        $this->push([
            'level' => $level,
            'message' => Values::truncate(Values::printable($message), 2000),
        ]);
    }

    public function addException(\Throwable $exception): void
    {
        $this->push([
            'level' => self::ERROR,
            'message' => $exception::class.': '.Values::printable($exception->getMessage()),
            // Relative, like a query's origin: the absolute prefix is the same
            // on every line and is usually longer than the part that differs.
            'file' => $this->paths->relative($exception->getFile()),
            'line' => $exception->getLine(),
            'trace' => $this->formatTrace($exception),
        ]);
    }

    public function collect(ServerRequestInterface $request, ResponseInterface $response): array
    {
        $messages = $this->messages;

        if ($this->dropped > 0) {
            $messages[] = [
                'time' => microtime(true),
                'level' => self::WARNING,
                'message' => "{$this->dropped} further messages were not recorded (limit: ".self::LIMIT.').',
            ];
        }

        return [
            'count' => count($this->messages) + $this->dropped,
            'messages' => $messages,
        ];
    }

    /**
     * @param array<string, mixed> $entry
     */
    protected function push(array $entry): void
    {
        if (count($this->messages) >= self::LIMIT) {
            $this->dropped++;

            return;
        }

        $this->messages[] = ['time' => microtime(true)] + $entry;
    }

    /**
     * @return list<string>
     */
    protected function formatTrace(\Throwable $exception): array
    {
        $frames = [];

        foreach (array_slice($exception->getTrace(), 0, 20) as $frame) {
            $call = ($frame['class'] ?? '').($frame['type'] ?? '').($frame['function'] ?? '');
            $where = isset($frame['file']) ? $this->paths->reference($frame['file'], $frame['line'] ?? 0) : '[internal]';

            $frames[] = $call.' — '.$where;
        }

        return $frames;
    }
}
