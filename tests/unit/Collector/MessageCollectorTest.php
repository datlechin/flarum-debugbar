<?php

/*
 * This file is part of datlechin/flarum-debugbar.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\FlarumDebugbar\Tests\unit\Collector;

use Datlechin\FlarumDebugbar\Collector\MessageCollector;
use Datlechin\FlarumDebugbar\Tests\unit\MakesHttpMessages;
use Datlechin\FlarumDebugbar\Tests\unit\MakesPaths;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class MessageCollectorTest extends TestCase
{
    use MakesHttpMessages;
    use MakesPaths;

    private function collector(): MessageCollector
    {
        return new MessageCollector($this->paths());
    }

    private function collect(MessageCollector $collector): array
    {
        return $collector->collect($this->request(), $this->response());
    }

    #[Test]
    public function it_records_a_message_at_a_level(): void
    {
        $collector = $this->collector();
        $collector->add('Loading widgets', MessageCollector::WARNING);

        $message = $this->collect($collector)['messages'][0];

        $this->assertSame('Loading widgets', $message['message']);
        $this->assertSame('warning', $message['level']);
        $this->assertArrayHasKey('time', $message);
    }

    #[Test]
    public function it_defaults_to_info(): void
    {
        $collector = $this->collector();
        $collector->add('Something happened');

        $this->assertSame('info', $this->collect($collector)['messages'][0]['level']);
    }

    #[Test]
    public function it_records_an_exception_with_where_it_came_from(): void
    {
        $collector = $this->collector();
        $exception = new \RuntimeException('it broke');

        $collector->addException($exception);

        $message = $this->collect($collector)['messages'][0];

        $this->assertSame('error', $message['level']);
        $this->assertSame(\RuntimeException::class.': it broke', $message['message']);
        $this->assertSame($this->paths()->relative($exception->getFile()), $message['file']);
        $this->assertSame($exception->getLine(), $message['line']);
        $this->assertNotEmpty($message['trace']);
        $this->assertContainsOnlyString($message['trace']);
    }

    #[Test]
    public function it_shortens_paths_to_the_part_that_identifies_them(): void
    {
        // The prefix that says where the forum is installed is the same on
        // every line and is usually longer than the part that differs. The
        // queries panel already reports origins this way; the two collectors
        // disagreeing about it was the inconsistency.
        $collector = new MessageCollector($this->paths('/srv/forum'));
        $collector->addException(new \RuntimeException('it broke'));

        $message = $this->collect($collector)['messages'][0];

        $this->assertStringNotContainsString('/srv/forum/', $message['file']);
        $this->assertStringNotContainsString('/srv/forum/', implode("\n", $message['trace']));
    }

    #[Test]
    public function it_keeps_messages_in_the_order_they_were_logged(): void
    {
        $collector = $this->collector();
        $collector->add('first');
        $collector->add('second');
        $collector->add('third');

        $this->assertSame(['first', 'second', 'third'], array_column($this->collect($collector)['messages'], 'message'));
    }

    #[Test]
    public function it_stops_recording_before_a_logging_loop_fills_the_disk(): void
    {
        $collector = $this->collector();

        foreach (range(1, 550) as $n) {
            $collector->add("message {$n}");
        }

        $data = $this->collect($collector);

        $this->assertSame(550, $data['count']);

        // 500 kept, plus a final line saying what happened — silently dropping
        // fifty messages would leave someone hunting for one that was logged.
        $this->assertCount(501, $data['messages']);
        $this->assertSame('warning', $data['messages'][500]['level']);
        $this->assertStringContainsString('50 further messages', $data['messages'][500]['message']);
    }

    #[Test]
    public function it_strips_bytes_that_would_break_the_profile(): void
    {
        $collector = $this->collector();
        $collector->add("before\x00after");

        $this->assertIsString(json_encode($this->collect($collector)));
        $this->assertSame('beforeafter', $this->collect($collector)['messages'][0]['message']);
    }

    #[Test]
    public function it_records_nothing_when_nothing_was_logged(): void
    {
        $data = $this->collect($this->collector());

        $this->assertSame(0, $data['count']);
        $this->assertSame([], $data['messages']);
    }
}
