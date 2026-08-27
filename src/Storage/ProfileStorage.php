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
 * Where profiles live between the request that produced them and the frontend
 * that reads them back.
 */
interface ProfileStorage
{
    public function save(Profile $profile): void;

    public function find(string $id): ?Profile;

    /**
     * The most recently stored profiles, newest first, as summaries.
     *
     * @return list<array<string, mixed>>
     */
    public function recent(int $limit = 25): array;

    /**
     * Discard every stored profile. Returns how many were removed.
     */
    public function clear(): int;
}
