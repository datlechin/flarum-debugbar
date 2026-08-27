<?php

/*
 * This file is part of datlechin/flarum-debugbar.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\FlarumDebugbar\Tests\unit\Storage;

use Datlechin\FlarumDebugbar\Profile;
use Datlechin\FlarumDebugbar\Storage\FileProfileStorage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class FileProfileStorageTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/flarum-debugbar-'.bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.'/*') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($this->directory);
    }

    private function storage(int $keep = 50): FileProfileStorage
    {
        return new FileProfileStorage($this->directory, $keep);
    }

    private function profile(string $id, array $data = ['queries' => ['count' => 1]]): Profile
    {
        return new Profile(
            id: $id,
            time: 1787816682.5,
            method: 'GET',
            uri: '/d/1-hello',
            status: 200,
            duration: 0.25,
            memory: 2097152,
            data: $data,
        );
    }

    private function id(int $n): string
    {
        return str_pad(dechex($n), 16, '0', STR_PAD_LEFT);
    }

    #[Test]
    public function it_stores_and_reads_back_a_profile(): void
    {
        $storage = $this->storage();
        $storage->save($this->profile($this->id(1)));

        $found = $storage->find($this->id(1));

        $this->assertInstanceOf(Profile::class, $found);
        $this->assertSame('/d/1-hello', $found->uri);
        $this->assertSame(200, $found->status);
        $this->assertSame(0.25, $found->duration);
        $this->assertSame(['queries' => ['count' => 1]], $found->data);
    }

    #[Test]
    public function it_creates_its_directory_on_first_use(): void
    {
        $this->assertDirectoryDoesNotExist($this->directory);

        $this->storage()->save($this->profile($this->id(1)));

        $this->assertDirectoryExists($this->directory);
    }

    #[Test]
    public function it_returns_nothing_for_a_profile_it_does_not_have(): void
    {
        $this->assertNull($this->storage()->find($this->id(9)));
    }

    #[Test]
    #[DataProvider('hostileIds')]
    public function it_refuses_ids_that_are_not_its_own(string $id): void
    {
        // Ids are used to build file paths, so anything that is not in the
        // exact shape this class issues is rejected before it reaches the
        // filesystem — the id arrives from a URL.
        $this->assertNull($this->storage()->find($id));
    }

    public static function hostileIds(): array
    {
        return [
            'traversal' => ['../../config'],
            'absolute' => ['/etc/passwd'],
            'the index itself' => ['index'],
            'too short' => ['abc'],
            'not hex' => ['zzzzzzzzzzzzzzzz'],
            'uppercase' => ['ABCDEF0123456789'],
            'empty' => [''],
            'null byte' => ["0123456789abcdef\0"],
        ];
    }

    #[Test]
    public function it_lists_recent_profiles_newest_first(): void
    {
        $storage = $this->storage();

        foreach ([1, 2, 3] as $n) {
            $storage->save($this->profile($this->id($n)));
        }

        $recent = $storage->recent();

        $this->assertSame([$this->id(3), $this->id(2), $this->id(1)], array_column($recent, 'id'));
    }

    #[Test]
    public function the_listing_is_a_summary_rather_than_the_whole_profile(): void
    {
        $storage = $this->storage();
        $storage->save($this->profile($this->id(1)));

        // Reading forty profiles off disk to draw a list of forty lines is
        // what the index exists to avoid.
        $this->assertArrayNotHasKey('data', $storage->recent()[0]);
        $this->assertSame(['id', 'time', 'method', 'uri', 'status', 'duration', 'memory'], array_keys($storage->recent()[0]));
    }

    #[Test]
    public function it_honours_the_requested_limit(): void
    {
        $storage = $this->storage();

        foreach (range(1, 5) as $n) {
            $storage->save($this->profile($this->id($n)));
        }

        $this->assertCount(2, $storage->recent(2));
        $this->assertCount(0, $storage->recent(0));
    }

    #[Test]
    public function it_prunes_old_profiles_as_new_ones_arrive(): void
    {
        $storage = $this->storage(keep: 3);

        foreach (range(1, 5) as $n) {
            $storage->save($this->profile($this->id($n)));
        }

        $this->assertSame([$this->id(5), $this->id(4), $this->id(3)], array_column($storage->recent(), 'id'));

        // The files must go too, not just the index entries, or storage grows
        // without bound while appearing bounded.
        $this->assertNull($storage->find($this->id(1)));
        $this->assertFileDoesNotExist($this->directory.'/'.$this->id(1).'.json');
        $this->assertFileExists($this->directory.'/'.$this->id(5).'.json');
    }

    #[Test]
    public function it_clears_everything_and_reports_how_many_profiles_went(): void
    {
        $storage = $this->storage();

        foreach (range(1, 3) as $n) {
            $storage->save($this->profile($this->id($n)));
        }

        // Three profiles, not four: the index is not a profile.
        $this->assertSame(3, $storage->clear());
        $this->assertSame([], $storage->recent());
        $this->assertNull($storage->find($this->id(1)));
    }

    #[Test]
    public function it_survives_a_profile_holding_something_json_cannot_carry(): void
    {
        $storage = $this->storage();

        $storage->save($this->profile($this->id(1), [
            'queries' => ['sql' => "select \xB1\x31 from t"],
            'messages' => ['count' => 2],
        ]));

        $found = $storage->find($this->id(1));

        // The panel that held the bad value may lose it, but the rest of the
        // profile must still be readable.
        $this->assertInstanceOf(Profile::class, $found);
        $this->assertSame(['count' => 2], $found->data['messages']);
    }

    #[Test]
    public function it_reads_nothing_from_a_corrupt_file(): void
    {
        $storage = $this->storage();
        $storage->save($this->profile($this->id(1)));

        file_put_contents($this->directory.'/'.$this->id(1).'.json', '{not json');

        $this->assertNull($storage->find($this->id(1)));
    }

    #[Test]
    public function it_reads_nothing_from_a_corrupt_index(): void
    {
        $storage = $this->storage();
        $storage->save($this->profile($this->id(1)));

        file_put_contents($this->directory.'/index.json', 'garbage');

        $this->assertSame([], $storage->recent());

        // And it recovers on the next write rather than staying broken.
        $storage->save($this->profile($this->id(2)));
        $this->assertSame([$this->id(2)], array_column($storage->recent(), 'id'));
    }
}
