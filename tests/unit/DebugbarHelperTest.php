<?php

namespace Datlechin\FlarumDebugbar\Tests\unit;

use Datlechin\FlarumDebugbar\DebugbarHelper;
use DebugBar\StandardDebugBar;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DebugbarHelperTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        DebugbarHelper::setDebugBar(null);
    }

    #[Test]
    public function it_is_disabled_when_no_debugbar_set(): void
    {
        $this->assertFalse(DebugbarHelper::isEnabled());
    }

    #[Test]
    public function it_is_enabled_when_debugbar_set(): void
    {
        DebugbarHelper::setDebugBar(new StandardDebugBar());

        $this->assertTrue(DebugbarHelper::isEnabled());
    }

    #[Test]
    public function messages_noop_when_disabled(): void
    {
        // Should not throw
        DebugbarHelper::info('test');
        DebugbarHelper::warning('test');
        DebugbarHelper::error('test');
        DebugbarHelper::debug('test');

        $this->assertTrue(true);
    }

    #[Test]
    public function messages_are_recorded_when_enabled(): void
    {
        $debugbar = new StandardDebugBar();
        DebugbarHelper::setDebugBar($debugbar);

        DebugbarHelper::info('hello');
        DebugbarHelper::warning('warn');

        $data = $debugbar['messages']->getMessages();

        $this->assertCount(2, $data);
        $this->assertEquals('hello', $data[0]['message']);
        $this->assertEquals('warn', $data[1]['message']);
    }

    #[Test]
    public function measures_noop_when_disabled(): void
    {
        // Should not throw
        DebugbarHelper::startMeasure('test', 'Test');
        DebugbarHelper::stopMeasure('test');

        $this->assertTrue(true);
    }

    #[Test]
    public function measures_are_recorded_when_enabled(): void
    {
        $debugbar = new StandardDebugBar();
        DebugbarHelper::setDebugBar($debugbar);

        DebugbarHelper::startMeasure('op', 'Operation');
        usleep(1000);
        DebugbarHelper::stopMeasure('op');

        $data = $debugbar['time']->collect();

        $this->assertNotEmpty($data['measures']);
    }

    #[Test]
    public function stop_measure_does_not_throw_for_unknown_name(): void
    {
        $debugbar = new StandardDebugBar();
        DebugbarHelper::setDebugBar($debugbar);

        // Should not throw
        DebugbarHelper::stopMeasure('nonexistent');

        $this->assertTrue(true);
    }

    #[Test]
    public function add_exception_noop_when_disabled(): void
    {
        DebugbarHelper::addException(new \RuntimeException('test'));

        $this->assertTrue(true);
    }

    #[Test]
    public function add_exception_is_recorded_when_enabled(): void
    {
        $debugbar = new StandardDebugBar();
        DebugbarHelper::setDebugBar($debugbar);

        DebugbarHelper::addException(new \RuntimeException('boom'));

        $data = $debugbar['exceptions']->collect();

        $this->assertCount(1, $data['exceptions']);
        $this->assertStringContainsString('boom', $data['exceptions'][0]['message']);
    }
}
