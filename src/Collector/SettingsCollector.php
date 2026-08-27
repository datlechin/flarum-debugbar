<?php

/*
 * This file is part of datlechin/flarum-debugbar.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\FlarumDebugbar\Collector;

use Datlechin\FlarumDebugbar\Support\Values;
use Flarum\Extension\ExtensionManager;
use Flarum\Settings\SettingsRepositoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Everything in the settings table, grouped by the extension that owns it.
 *
 * Flarum namespaces an extension's settings by prefixing them with its id and
 * a dot. The prefix is matched against the extensions that are actually
 * installed rather than against "anything before a dot", because core has
 * dotted keys of its own — `assets_dirty.admin` is core's, and grouping on the
 * dot alone invented an `assets_dirty` extension to put it under.
 */
class SettingsCollector implements CollectorInterface
{
    public const CORE_GROUP = 'core';

    public function __construct(
        protected SettingsRepositoryInterface $settings,
        protected ExtensionManager $extensions,
    ) {
    }

    public function name(): string
    {
        return 'settings';
    }

    public function collect(ServerRequestInterface $request, ResponseInterface $response): array
    {
        $grouped = [];
        $count = 0;

        foreach ($this->settings->all() as $key => $value) {
            $key = (string) $key;
            $count++;

            $group = $this->group($key);

            $grouped[$group][] = [
                'key' => $key,
                // The group heading already names the extension, so repeating
                // its id on every row underneath costs most of the width of
                // the row and says nothing.
                'name' => $group === self::CORE_GROUP ? $key : substr($key, strlen($group) + 1),
                'value' => Values::redact($key, $value),
                'sensitive' => Values::isSensitive($key),
            ];
        }

        ksort($grouped);

        $groups = [];

        // Core's settings are the ones a reader is least often looking for
        // when they open this panel, and there are more of them than any
        // single extension has, so they go last rather than under `c`.
        foreach ([...array_diff(array_keys($grouped), [self::CORE_GROUP]), self::CORE_GROUP] as $name) {
            if (! isset($grouped[$name])) {
                continue;
            }

            usort($grouped[$name], fn (array $a, array $b) => $a['key'] <=> $b['key']);

            $groups[] = ['name' => $name, 'settings' => $grouped[$name]];
        }

        return [
            'count' => $count,
            'groups' => $groups,
        ];
    }

    protected function group(string $key): string
    {
        $prefix = strstr($key, '.', true);

        if ($prefix === false || $prefix === '') {
            return self::CORE_GROUP;
        }

        return $this->extensions->getExtension($prefix) ? $prefix : self::CORE_GROUP;
    }
}
