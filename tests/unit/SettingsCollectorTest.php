<?php

namespace Datlechin\FlarumDebugbar\Tests\unit;

use Datlechin\FlarumDebugbar\Collector\SettingsCollector;
use Flarum\Settings\SettingsRepositoryInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SettingsCollectorTest extends TestCase
{
    #[Test]
    public function it_groups_settings_by_prefix(): void
    {
        $settings = $this->createMock(SettingsRepositoryInterface::class);
        $settings->method('all')->willReturn([
            'forum_title' => 'Test Forum',
            'flarum-tags.min_primary_tags' => '1',
            'flarum-likes.like_own_post' => '1',
        ]);

        $collector = new SettingsCollector($settings);
        $data = $collector->collect();

        $this->assertArrayHasKey('[_core] forum_title', $data);
        $this->assertArrayHasKey('[flarum-tags] flarum-tags.min_primary_tags', $data);
        $this->assertArrayHasKey('[flarum-likes] flarum-likes.like_own_post', $data);
    }

    #[Test]
    public function it_masks_sensitive_settings(): void
    {
        $settings = $this->createMock(SettingsRepositoryInterface::class);
        $settings->method('all')->willReturn([
            'mail_password' => 'super-secret',
            'api_token' => 'abc123',
            'smtp_host' => 'mail.example.com',
            'forum_title' => 'My Forum',
            'mail_secret_key' => 'key123',
        ]);

        $collector = new SettingsCollector($settings);
        $data = $collector->collect();

        $this->assertEquals('********', $data['[_core] mail_password']);
        $this->assertEquals('********', $data['[_core] api_token']);
        $this->assertEquals('********', $data['[_core] smtp_host']);
        $this->assertEquals('********', $data['[_core] mail_secret_key']);
        $this->assertEquals('My Forum', $data['[_core] forum_title']);
    }

    #[Test]
    public function it_does_not_mask_empty_values(): void
    {
        $settings = $this->createMock(SettingsRepositoryInterface::class);
        $settings->method('all')->willReturn([
            'mail_password' => '',
        ]);

        $collector = new SettingsCollector($settings);
        $data = $collector->collect();

        $this->assertEquals('', $data['[_core] mail_password']);
    }

    #[Test]
    public function it_truncates_long_values(): void
    {
        $settings = $this->createMock(SettingsRepositoryInterface::class);
        $settings->method('all')->willReturn([
            'custom_less' => str_repeat('a', 300),
        ]);

        $collector = new SettingsCollector($settings);
        $data = $collector->collect();

        $this->assertStringContainsString('... (300 chars)', $data['[_core] custom_less']);
    }

    #[Test]
    public function it_returns_correct_name(): void
    {
        $settings = $this->createMock(SettingsRepositoryInterface::class);
        $settings->method('all')->willReturn([]);

        $this->assertEquals('settings', (new SettingsCollector($settings))->getName());
    }
}
