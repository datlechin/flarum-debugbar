<?php

/*
 * This file is part of datlechin/flarum-debugbar.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\FlarumDebugbar\Tests\unit\Support;

use Datlechin\FlarumDebugbar\Support\Settings;
use Datlechin\FlarumDebugbar\Tests\unit\ArraySettingsRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SettingsTest extends TestCase
{
    #[Test]
    #[DataProvider('retention')]
    public function it_keeps_retention_within_bounds(mixed $stored, int $expected): void
    {
        $settings = new ArraySettingsRepository([Settings::MAX_PROFILES => $stored]);

        $this->assertSame($expected, Settings::maxProfiles($settings));
    }

    public static function retention(): array
    {
        return [
            'a sensible value is used as-is' => ['25', 25],
            'zero would mean a bar that never works' => ['0', Settings::DEFAULT_MAX_PROFILES],
            'unset falls back to the default' => [null, Settings::DEFAULT_MAX_PROFILES],
            'nonsense falls back to the default' => ['banana', Settings::DEFAULT_MAX_PROFILES],
            'negative is clamped up' => ['-10', Settings::MIN_MAX_PROFILES],
            'unbounded is clamped down' => ['999999', Settings::MAX_MAX_PROFILES],
        ];
    }

    #[Test]
    public function it_reads_the_disabled_collector_list(): void
    {
        $settings = new ArraySettingsRepository([Settings::DISABLED_COLLECTORS => '["events","cache"]']);

        $this->assertSame(['events', 'cache'], Settings::disabledCollectors($settings));
    }

    #[Test]
    public function it_treats_an_unreadable_list_as_nothing_disabled(): void
    {
        // Everything on by default is the safe reading: a bar that quietly
        // stops collecting is much harder to diagnose than one that collects
        // something you did not ask for.
        foreach ([null, '', 'not json', '{"a":1}', '5'] as $stored) {
            $settings = new ArraySettingsRepository([Settings::DISABLED_COLLECTORS => $stored]);

            $this->assertSame([], Settings::disabledCollectors($settings), var_export($stored, true));
        }
    }

    #[Test]
    public function it_ignores_non_string_entries(): void
    {
        $settings = new ArraySettingsRepository([Settings::DISABLED_COLLECTORS => '["events",5,null,"cache"]']);

        $this->assertSame(['events', 'cache'], Settings::disabledCollectors($settings));
    }

    #[Test]
    public function every_default_is_for_a_key_this_class_names(): void
    {
        $keys = [Settings::MAX_PROFILES, Settings::DISABLED_COLLECTORS, Settings::TRACE_QUERIES, Settings::OPEN_BY_DEFAULT];

        $this->assertSame($keys, array_keys(Settings::defaults()));

        foreach ($keys as $key) {
            $this->assertStringStartsWith(Settings::PREFIX, $key);
        }
    }
}
