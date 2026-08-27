<?php

/*
 * This file is part of datlechin/flarum-debugbar.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\FlarumDebugbar\Support;

use Flarum\Settings\SettingsRepositoryInterface;

/**
 * The extension's own settings, in one place, so that the key a page writes is
 * provably the key the backend reads.
 */
final class Settings
{
    public const PREFIX = 'datlechin-debugbar.';

    /**
     * How many profiles to keep. Older ones are pruned as new ones arrive.
     */
    public const MAX_PROFILES = self::PREFIX.'max_profiles';

    /**
     * Collector names the administrator has switched off, as a JSON array.
     *
     * Storing what is *off* rather than what is on means a collector added
     * later — by an upgrade, or by another extension — starts out working,
     * which is what someone who installed it expects.
     */
    public const DISABLED_COLLECTORS = self::PREFIX.'disabled_collectors';

    /**
     * Whether to record where each query came from. This is the single most
     * useful thing the queries panel shows and the single most expensive thing
     * the debug bar does, so it is worth being able to turn off.
     */
    public const TRACE_QUERIES = self::PREFIX.'trace_queries';

    /**
     * Whether the bar starts expanded rather than collapsed to its handle.
     */
    public const OPEN_BY_DEFAULT = self::PREFIX.'open_by_default';

    public const DEFAULT_MAX_PROFILES = 50;

    public const MIN_MAX_PROFILES = 1;

    public const MAX_MAX_PROFILES = 500;

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            self::MAX_PROFILES => self::DEFAULT_MAX_PROFILES,
            self::DISABLED_COLLECTORS => '[]',
            self::TRACE_QUERIES => true,
            self::OPEN_BY_DEFAULT => false,
        ];
    }

    /**
     * The names of collectors that should not run.
     *
     * @return list<string>
     */
    public static function disabledCollectors(SettingsRepositoryInterface $settings): array
    {
        $raw = json_decode((string) $settings->get(self::DISABLED_COLLECTORS, '[]'), true);

        if (! is_array($raw)) {
            return [];
        }

        return array_values(array_filter($raw, 'is_string'));
    }

    public static function maxProfiles(SettingsRepositoryInterface $settings): int
    {
        $value = (int) $settings->get(self::MAX_PROFILES, self::DEFAULT_MAX_PROFILES);

        // Zero would mean "keep nothing", which reads as a broken debug bar
        // rather than as a setting; anything unbounded fills the disk.
        return max(self::MIN_MAX_PROFILES, min(self::MAX_MAX_PROFILES, $value ?: self::DEFAULT_MAX_PROFILES));
    }
}
