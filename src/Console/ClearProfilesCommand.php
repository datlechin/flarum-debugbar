<?php

/*
 * This file is part of datlechin/flarum-debugbar.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\FlarumDebugbar\Console;

use Datlechin\FlarumDebugbar\Storage\ProfileStorage;
use Flarum\Console\AbstractCommand;

/**
 * Throw away every stored profile.
 *
 * Retention already prunes as it goes, so this is for the times you want a
 * clean slate before reproducing something — or want the contents of
 * `storage/debugbar` gone.
 */
class ClearProfilesCommand extends AbstractCommand
{
    public function __construct(
        protected ProfileStorage $storage,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('debugbar:clear')
            ->setDescription('Delete all stored debug bar profiles');
    }

    protected function fire(): int
    {
        $cleared = $this->storage->clear();

        $this->info($cleared === 1
            ? 'Deleted 1 profile.'
            : "Deleted {$cleared} profiles.");

        return 0;
    }
}
