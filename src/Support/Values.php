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

/**
 * Turning arbitrary runtime values into something safe to write to a JSON file
 * and read back in a browser.
 *
 * Collectors hand us whatever the application happened to be holding: query
 * bindings, event payloads, setting values. Any of it may be a resource, a
 * closure, a model with a lazy relation, a megabyte of serialised data, or
 * invalid UTF-8 straight out of a BLOB column — none of which survive
 * `json_encode` intact, and some of which would pull the whole object graph
 * onto disk if we let them.
 */
final class Values
{
    /**
     * How much of any single value we are prepared to keep.
     */
    public const MAX_LENGTH = 300;

    /**
     * A short, printable description of a value.
     */
    public static function stringify(mixed $value, int $maxLength = self::MAX_LENGTH): string
    {
        $string = match (true) {
            $value === null => 'null',
            is_bool($value) => $value ? 'true' : 'false',
            is_string($value) => $value,
            is_int($value), is_float($value) => (string) $value,
            is_resource($value) => 'resource('.get_resource_type($value).')',
            $value instanceof \UnitEnum => $value::class.'::'.$value->name,
            $value instanceof \DateTimeInterface => $value->format('Y-m-d H:i:s'),
            $value instanceof \Closure => 'Closure',
            $value instanceof \Stringable => (string) $value,
            is_array($value) => self::encode($value),
            // Objects are named, never dumped. Almost everything that reaches
            // a collector is an Eloquent model, and a model is
            // `JsonSerializable`: serialising one would run every accessor and
            // pull every loaded relation into the profile.
            is_object($value) => $value::class,
            default => get_debug_type($value),
        };

        return self::truncate(self::printable($string), $maxLength);
    }

    /**
     * The same, but preserving the distinction between a string and a value
     * that merely reads like one — so the frontend can render `null` and
     * `"null"` differently.
     *
     * @return array{value: string, type: string}
     */
    public static function describe(mixed $value, int $maxLength = self::MAX_LENGTH): array
    {
        return [
            'value' => self::stringify($value, $maxLength),
            'type' => get_debug_type($value),
        ];
    }

    /**
     * Truncate to a length, saying how much was left out.
     */
    public static function truncate(string $value, int $maxLength = self::MAX_LENGTH): string
    {
        if ($maxLength <= 0 || mb_strlen($value) <= $maxLength) {
            return $value;
        }

        $omitted = mb_strlen($value) - $maxLength;

        return mb_substr($value, 0, $maxLength).'… ('.$omitted.' more characters)';
    }

    /**
     * Whether a key names something that should never be written to a profile.
     *
     * Profiles sit in `storage/` as world-readable JSON and are served back
     * over HTTP, so a password or an API token that passes through here would
     * outlive the request that carried it. Matching is deliberately on whole
     * words: a substring match on `key` alone would also redact
     * `keyboard_shortcuts` and `foreign_key`, which helps nobody.
     */
    public static function isSensitive(string $key): bool
    {
        static $pattern = '/(?:^|[^a-z])(?:'.
            'password|passwd|secret|token|api[_-]?key|private[_-]?key|access[_-]?key|'.
            'credential|authorization|auth[_-]?key|signature|salt|nonce|session[_-]?id|'.
            'cookie|csrf|xsrf|dsn|encryption'.
            ')(?:[^a-z]|$)/i';

        return (bool) preg_match($pattern, $key);
    }

    /**
     * A value with anything sensitive replaced.
     */
    public static function redact(string $key, mixed $value, int $maxLength = self::MAX_LENGTH): string
    {
        if (self::isSensitive($key) && $value !== null && $value !== '') {
            return '••••••••';
        }

        return self::stringify($value, $maxLength);
    }

    /**
     * Strip anything that would not survive a round trip through JSON.
     *
     * Invalid UTF-8 makes `json_encode` return false for the *entire* profile,
     * so a single binary column would otherwise cost us every other panel.
     * Control characters are dropped because they render as nothing useful and
     * can break out of the layout.
     */
    public static function printable(string $value): string
    {
        $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');

        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? $value;
    }

    private static function encode(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR)
            ?: 'array('.count($value).')';
    }
}
