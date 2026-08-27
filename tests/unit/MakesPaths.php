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

use Datlechin\FlarumDebugbar\Support\SourcePath;
use Flarum\Foundation\Paths;

/**
 * A fixed installation root, so tests that shorten file paths do not depend on
 * where this checkout happens to live.
 */
trait MakesPaths
{
    protected function paths(string $base = '/srv/forum'): SourcePath
    {
        return new SourcePath(new Paths([
            'base' => $base,
            'public' => $base.'/public',
            'storage' => $base.'/storage',
            'vendor' => $base.'/vendor',
        ]));
    }
}
