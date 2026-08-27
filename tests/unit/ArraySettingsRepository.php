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

use Flarum\Settings\SettingsRepositoryInterface;

/**
 * An in-memory settings repository, so the unit tests never need a database.
 */
class ArraySettingsRepository implements SettingsRepositoryInterface
{
    /**
     * @param array<string, mixed> $settings
     */
    public function __construct(
        protected array $settings = [],
    ) {
    }

    public function all(): array
    {
        return $this->settings;
    }

    public function get($key, $default = null): mixed
    {
        return $this->settings[$key] ?? $default;
    }

    public function set($key, $value): void
    {
        $this->settings[$key] = $value;
    }

    public function delete($keyLike): void
    {
        foreach (array_keys($this->settings) as $key) {
            if (fnmatch(str_replace('%', '*', $keyLike), (string) $key)) {
                unset($this->settings[$key]);
            }
        }
    }
}
