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

use Datlechin\FlarumDebugbar\Collector\SettingsCollector;
use Datlechin\FlarumDebugbar\Tests\unit\ArraySettingsRepository;
use Datlechin\FlarumDebugbar\Tests\unit\MakesHttpMessages;
use Flarum\Extension\Extension;
use Flarum\Extension\ExtensionManager;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SettingsCollectorTest extends TestCase
{
    use MakesHttpMessages;

    /**
     * The extensions the forum has installed. A prefix only makes a group if
     * it names one of these.
     *
     * @var list<string>
     */
    private array $installed = ['flarum-tags', 'acme-widgets', 'acme', 'zzz-extension', 'datlechin-keyboard-shortcuts'];

    /**
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    private function collect(array $settings): array
    {
        $extensions = $this->createStub(ExtensionManager::class);
        $extensions->method('getExtension')->willReturnCallback(
            fn (string $id) => in_array($id, $this->installed, true) ? $this->createStub(Extension::class) : null
        );

        $collector = new SettingsCollector(new ArraySettingsRepository($settings), $extensions);

        return $collector->collect($this->request(), $this->response());
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function grouped(array $settings): array
    {
        $grouped = [];

        foreach ($this->collect($settings)['groups'] as $group) {
            $grouped[$group['name']] = array_column($group['settings'], 'value', 'key');
        }

        return $grouped;
    }

    #[Test]
    public function it_groups_settings_by_the_extension_that_owns_them(): void
    {
        // Flarum namespaces an extension's settings by prefixing them with its
        // id and a dot, so a new extension groups correctly without this
        // collector knowing it exists.
        $grouped = $this->grouped([
            'forum_title' => 'Example',
            'flarum-tags.max_primary_tags' => '3',
            'acme-widgets.enabled' => '1',
        ]);

        $this->assertSame(['acme-widgets', 'flarum-tags', 'core'], array_keys($grouped));
        $this->assertSame(['flarum-tags.max_primary_tags' => '3'], $grouped['flarum-tags']);
        $this->assertSame(['forum_title' => 'Example'], $grouped['core']);
    }

    #[Test]
    public function a_dotted_core_key_does_not_invent_an_extension(): void
    {
        // `assets_dirty.admin` is core's. Grouping on "anything before a dot"
        // put it under an `assets_dirty` heading, as though some extension by
        // that name owned it.
        $grouped = $this->grouped([
            'assets_dirty' => '1',
            'assets_dirty.admin' => '1',
            'flarum-tags.max_primary_tags' => '3',
        ]);

        $this->assertSame(['flarum-tags', 'core'], array_keys($grouped));
        $this->assertSame(['assets_dirty', 'assets_dirty.admin'], array_keys($grouped['core']));
    }

    #[Test]
    public function it_strips_the_group_prefix_from_the_name_it_shows(): void
    {
        // The heading already names the extension; repeating it on every row
        // costs most of the width of the row and says nothing.
        $groups = $this->collect(['acme-widgets.enabled' => '1', 'forum_title' => 'Example'])['groups'];
        $names = [];

        foreach ($groups as $group) {
            $names[$group['name']] = array_column($group['settings'], 'name', 'key');
        }

        $this->assertSame(['acme-widgets.enabled' => 'enabled'], $names['acme-widgets']);

        // A core setting has no prefix to strip.
        $this->assertSame(['forum_title' => 'forum_title'], $names['core']);
    }

    #[Test]
    public function core_settings_come_last(): void
    {
        // There are more of them than any single extension has, and they are
        // the ones a reader is least often looking for when they open this
        // panel.
        $grouped = $this->grouped([
            'forum_title' => 'Example',
            'zzz-extension.setting' => 'x',
        ]);

        $this->assertSame(['zzz-extension', 'core'], array_keys($grouped));
    }

    #[Test]
    public function settings_are_ordered_within_their_group(): void
    {
        $grouped = $this->grouped([
            'acme.zebra' => '1',
            'acme.apple' => '2',
        ]);

        $this->assertSame(['acme.apple', 'acme.zebra'], array_keys($grouped['acme']));
    }

    #[Test]
    public function it_hides_credentials(): void
    {
        $grouped = $this->grouped([
            'mail_password' => 'hunter2',
            'acme.api_key' => 'sk-live-123',
            'forum_title' => 'Example',
        ]);

        $this->assertSame('••••••••', $grouped['core']['mail_password']);
        $this->assertSame('••••••••', $grouped['acme']['acme.api_key']);
        $this->assertSame('Example', $grouped['core']['forum_title']);
    }

    #[Test]
    public function it_marks_which_values_were_hidden(): void
    {
        $groups = $this->collect(['mail_password' => 'hunter2', 'forum_title' => 'Example'])['groups'];
        $settings = array_column($groups[0]['settings'], 'sensitive', 'key');

        $this->assertTrue($settings['mail_password']);
        $this->assertFalse($settings['forum_title']);
    }

    #[Test]
    public function it_does_not_hide_a_setting_merely_for_containing_the_word_key(): void
    {
        // The previous implementation matched a bare `key` substring, which
        // redacted every setting of the keyboard shortcuts extension.
        $grouped = $this->grouped(['datlechin-keyboard-shortcuts.help' => '?']);

        $this->assertSame('?', $grouped['datlechin-keyboard-shortcuts']['datlechin-keyboard-shortcuts.help']);
    }

    #[Test]
    public function it_truncates_a_very_long_value(): void
    {
        $grouped = $this->grouped(['custom_less' => str_repeat('a', 5000)]);

        $this->assertLessThan(400, strlen($grouped['core']['custom_less']));
        $this->assertStringContainsString('more characters', $grouped['core']['custom_less']);
    }

    #[Test]
    public function it_counts_every_setting(): void
    {
        $this->assertSame(3, $this->collect(['a' => 1, 'b.c' => 2, 'd' => 3])['count']);
    }

    #[Test]
    public function it_copes_with_an_empty_settings_table(): void
    {
        $data = $this->collect([]);

        $this->assertSame(0, $data['count']);
        $this->assertSame([], $data['groups']);
    }
}
