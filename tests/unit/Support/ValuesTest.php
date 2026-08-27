<?php

/*
 * This file is part of datlechin/flarum-debugbar.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\FlarumDebugbar\Tests\unit\Support;

use Datlechin\FlarumDebugbar\Support\Values;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ValuesTest extends TestCase
{
    #[Test]
    #[DataProvider('scalars')]
    public function it_describes_scalars(mixed $value, string $expected): void
    {
        $this->assertSame($expected, Values::stringify($value));
    }

    public static function scalars(): array
    {
        return [
            'null' => [null, 'null'],
            'true' => [true, 'true'],
            'false' => [false, 'false'],
            'integer' => [42, '42'],
            'float' => [1.5, '1.5'],
            'string' => ['hello', 'hello'],
            'empty string' => ['', ''],
            'array' => [['a' => 1], '{"a":1}'],
            'list' => [[1, 2], '[1,2]'],
        ];
    }

    #[Test]
    public function it_names_objects_rather_than_dumping_them(): void
    {
        // An Eloquent model is `JsonSerializable`, so serialising one would
        // run every accessor and pull every loaded relation into the profile.
        $this->assertSame(ExplodingSerializable::class, Values::stringify(new ExplodingSerializable()));
    }

    #[Test]
    public function it_formats_dates_and_enums_readably(): void
    {
        $this->assertSame('2026-08-27 09:30:00', Values::stringify(new \DateTimeImmutable('2026-08-27 09:30:00')));
        $this->assertSame(ValuesTestEnum::class.'::Second', Values::stringify(ValuesTestEnum::Second));
    }

    #[Test]
    public function it_truncates_long_values_and_says_how_much_was_left_out(): void
    {
        $result = Values::stringify(str_repeat('a', 40), 10);

        $this->assertSame(str_repeat('a', 10).'… (30 more characters)', $result);
    }

    #[Test]
    public function it_leaves_values_at_the_limit_alone(): void
    {
        $this->assertSame('abcde', Values::truncate('abcde', 5));
    }

    #[Test]
    public function it_strips_bytes_that_would_break_the_whole_profile(): void
    {
        // A single invalid byte from a BLOB column makes `json_encode` return
        // false for the *entire* profile, costing every other panel. What
        // matters is that the result encodes and that the readable text
        // survives — invalid bytes become mbstring's substitute character
        // rather than vanishing, which at least shows something was there.
        $cleaned = Values::printable("valid\x00\x07".chr(0xFF).'text');

        $this->assertIsString(json_encode(['value' => $cleaned]));
        $this->assertStringStartsWith('valid', $cleaned);
        $this->assertStringEndsWith('text', $cleaned);
        $this->assertDoesNotMatchRegularExpression('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $cleaned);
    }

    #[Test]
    public function it_keeps_non_ascii_text(): void
    {
        $this->assertSame('café — 日本語', Values::printable('café — 日本語'));
    }

    #[Test]
    #[DataProvider('sensitiveKeys')]
    public function it_recognises_credentials(string $key, bool $expected): void
    {
        $this->assertSame($expected, Values::isSensitive($key), $key);
    }

    public static function sensitiveKeys(): array
    {
        return [
            ['password', true],
            ['mail_password', true],
            ['MAIL_PASSWORD', true],
            ['api_key', true],
            ['apiKey', true],
            ['access-token', true],
            ['Authorization', true],
            ['Cookie', true],
            ['X-CSRF-Token', true],
            ['session_id', true],

            // The previous implementation matched a bare `key` substring,
            // which redacted every one of these.
            ['datlechin-keyboard-shortcuts.help', false],
            ['foreign_key_checks', false],
            ['monkey', false],
            ['keyboard', false],
            ['forum_title', false],
            ['theme_primary_color', false],
        ];
    }

    #[Test]
    public function it_redacts_sensitive_values_but_not_empty_ones(): void
    {
        $this->assertSame('••••••••', Values::redact('mail_password', 'hunter2'));

        // An empty credential is worth being able to see, because "not set" is
        // usually the answer to why mail is not working.
        $this->assertSame('', Values::redact('mail_password', ''));
        $this->assertSame('open', Values::redact('forum_title', 'open'));
    }
}

enum ValuesTestEnum
{
    case First;
    case Second;
}

/**
 * Stands in for an Eloquent model: serialisable in principle, ruinous in
 * practice.
 */
class ExplodingSerializable implements \JsonSerializable
{
    public function jsonSerialize(): mixed
    {
        throw new \LogicException('an object must never be serialised into a profile');
    }
}
