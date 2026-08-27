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

use Flarum\Foundation\Paths;

/**
 * File paths, shortened to the part that identifies them.
 *
 * Every path a collector reports is inside the installation, so the prefix
 * that says where the installation lives is the same on every line and is
 * often longer than the part that differs — a stack frame reads as
 * `/Users/someone/Sites/forum/vendor/flarum/core/src/…` when
 * `vendor/flarum/core/src/…` is the whole of what anyone needs.
 */
class SourcePath
{
    protected string $base;

    public function __construct(Paths $paths)
    {
        $this->base = rtrim(str_replace('\\', '/', $paths->base), '/').'/';
    }

    public function relative(string $file): string
    {
        $file = str_replace('\\', '/', $file);

        return str_starts_with($file, $this->base) ? substr($file, strlen($this->base)) : $file;
    }

    /**
     * A `path:line` reference, as it would be typed into an editor.
     */
    public function reference(string $file, int|string|null $line): string
    {
        return $this->relative($file).':'.($line ?? 0);
    }
}
