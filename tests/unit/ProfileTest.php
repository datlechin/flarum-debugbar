<?php

/*
 * This file is part of datlechin/flarum-debugbar.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\FlarumDebugbar\Tests\unit;

use Datlechin\FlarumDebugbar\Profile;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ProfileTest extends TestCase
{
    private function profile(): Profile
    {
        return new Profile(
            id: 'abcdef0123456789',
            time: 1787816682.5,
            method: 'GET',
            uri: '/d/1-hello',
            status: 200,
            duration: 0.125,
            memory: 2097152,
            data: ['queries' => ['count' => 3]],
        );
    }

    #[Test]
    public function it_survives_a_round_trip_through_json(): void
    {
        // A profile is written to a file and read back a moment later, so
        // anything that does not survive that is not really in the profile.
        $original = $this->profile();

        $restored = Profile::fromArray(json_decode(json_encode($original->toArray()), true));

        $this->assertEquals($original, $restored);
    }

    #[Test]
    public function the_summary_carries_enough_to_recognise_a_request_by(): void
    {
        $summary = $this->profile()->summary();

        $this->assertSame(['id', 'time', 'method', 'uri', 'status', 'duration', 'memory'], array_keys($summary));

        // But not the collectors' data — reading forty profiles off disk to
        // draw forty lines is what the summary exists to avoid.
        $this->assertArrayNotHasKey('data', $summary);
    }

    #[Test]
    public function it_reads_back_a_profile_written_by_an_older_version(): void
    {
        // Stored profiles outlive an upgrade, and a missing key should mean a
        // sensible default rather than an error in the middle of the bar.
        $restored = Profile::fromArray(['id' => 'abcdef0123456789']);

        $this->assertSame('abcdef0123456789', $restored->id);
        $this->assertSame('GET', $restored->method);
        $this->assertSame(0, $restored->status);
        $this->assertSame([], $restored->data);
    }

    #[Test]
    public function it_ignores_a_data_key_that_is_not_a_map(): void
    {
        $this->assertSame([], Profile::fromArray(['id' => 'x', 'data' => 'nonsense'])->data);
    }
}
