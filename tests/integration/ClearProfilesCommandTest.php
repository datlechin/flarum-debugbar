<?php

/*
 * This file is part of datlechin/flarum-debugbar.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\FlarumDebugbar\Tests\integration;

use Datlechin\FlarumDebugbar\Storage\ProfileStorage;
use Flarum\Testing\integration\ConsoleTestCase;
use PHPUnit\Framework\Attributes\Test;

class ClearProfilesCommandTest extends ConsoleTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('datlechin-debugbar');
        $this->config('debug', true);
    }

    protected function storage(): ProfileStorage
    {
        return $this->app()->getContainer()->make(ProfileStorage::class);
    }

    #[Test]
    public function it_deletes_the_stored_profiles(): void
    {
        $this->send($this->request('GET', '/api/discussions'));

        $this->assertNotEmpty($this->storage()->recent(50));

        $output = $this->runCommand(['command' => 'debugbar:clear']);

        $this->assertStringContainsString('Deleted', $output);
        $this->assertSame([], $this->storage()->recent(50));
    }

    #[Test]
    public function it_is_content_to_find_nothing(): void
    {
        $this->storage()->clear();

        $this->assertStringContainsString('Deleted 0 profiles', $this->runCommand(['command' => 'debugbar:clear']));
    }

    #[Test]
    public function it_says_one_profile_rather_than_1_profiles(): void
    {
        $this->storage()->clear();
        $this->send($this->request('GET', '/api/discussions'));

        $this->assertStringContainsString('Deleted 1 profile.', $this->runCommand(['command' => 'debugbar:clear']));
    }
}
