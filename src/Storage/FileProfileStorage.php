<?php

/*
 * This file is part of datlechin/flarum-debugbar.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\FlarumDebugbar\Storage;

use Datlechin\FlarumDebugbar\Profile;

/**
 * Profiles as JSON files under `storage/debugbar`, alongside an index of their
 * summaries.
 *
 * The index exists so the request picker can be listed without opening (and
 * parsing) every profile on disk — a profile is a few hundred kilobytes, a
 * summary is a hundred bytes. It doubles as the retention list: writing a
 * profile trims the index to `$keep` entries and deletes the files that fell
 * off the end, so history is bounded without a scheduled task.
 */
class FileProfileStorage implements ProfileStorage
{
    /**
     * Profile ids are used to build file paths, so they are only ever accepted
     * in the shape this class issues them in.
     */
    private const ID_PATTERN = '/^[a-f0-9]{16}$/';

    private const INDEX = 'index.json';

    public function __construct(
        protected string $directory,
        protected int $keep = 50,
    ) {
    }

    public function save(Profile $profile): void
    {
        if (! $this->ensureDirectory()) {
            return;
        }

        file_put_contents(
            $this->path($profile->id),
            json_encode($profile->toArray(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR),
            LOCK_EX
        );

        foreach ($this->addToIndex($profile) as $expired) {
            @unlink($this->path($expired));
        }
    }

    public function find(string $id): ?Profile
    {
        if (! preg_match(self::ID_PATTERN, $id)) {
            return null;
        }

        $raw = $this->readJson($this->path($id));

        return $raw === null ? null : Profile::fromArray($raw);
    }

    public function recent(int $limit = 25): array
    {
        return array_slice($this->readIndex(), 0, max(0, $limit));
    }

    public function clear(): int
    {
        $files = glob($this->directory.'/*.json') ?: [];

        $removed = 0;

        foreach ($files as $file) {
            if (@unlink($file)) {
                $removed++;
            }
        }

        // The index is one of the files just deleted; it is not a profile, so
        // it should not be counted as one.
        return max(0, $removed - (int) in_array($this->directory.'/'.self::INDEX, $files, true));
    }

    /**
     * Record this profile in the index and return the ids that retention just
     * pushed off the end.
     *
     * The whole read-modify-write runs under an exclusive lock: two requests
     * finishing at once would otherwise each write an index built from the
     * state before the other, and one of the two profiles would be orphaned on
     * disk with nothing pointing at it.
     *
     * @return list<string>
     */
    protected function addToIndex(Profile $profile): array
    {
        $handle = @fopen($this->directory.'/'.self::INDEX, 'c+');

        if ($handle === false) {
            return [];
        }

        try {
            if (! flock($handle, LOCK_EX)) {
                return [];
            }

            $contents = stream_get_contents($handle);
            $summaries = $this->decodeIndex($contents === false ? '' : $contents);

            array_unshift($summaries, $profile->summary());

            $expired = array_slice($summaries, $this->keep);
            $summaries = array_slice($summaries, 0, $this->keep);

            rewind($handle);
            ftruncate($handle, 0);
            fwrite($handle, json_encode($summaries, JSON_UNESCAPED_SLASHES) ?: '[]');
            fflush($handle);

            return array_values(array_filter(array_column($expired, 'id'), 'is_string'));
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function readIndex(): array
    {
        $raw = @file_get_contents($this->directory.'/'.self::INDEX);

        return $this->decodeIndex($raw === false ? '' : $raw);
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function decodeIndex(string $contents): array
    {
        $decoded = json_decode($contents, true);

        if (! is_array($decoded)) {
            return [];
        }

        return array_values(array_filter($decoded, 'is_array'));
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function readJson(string $path): ?array
    {
        $raw = @file_get_contents($path);

        if ($raw === false) {
            return null;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    protected function path(string $id): string
    {
        return $this->directory.'/'.$id.'.json';
    }

    protected function ensureDirectory(): bool
    {
        if (is_dir($this->directory)) {
            return true;
        }

        // The `is_dir` re-check covers the race where a concurrent request
        // created the directory between our check and our `mkdir`.
        return @mkdir($this->directory, 0755, true) || is_dir($this->directory);
    }
}
