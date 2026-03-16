<?php

namespace Datlechin\FlarumDebugbar\Console;

use Flarum\Console\AbstractCommand;
use Flarum\Foundation\Paths;

class ClearStorageCommand extends AbstractCommand
{
    public function __construct(
        protected Paths $paths,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('debugbar:clear')
            ->setDescription('Clear stored debugbar request data');
    }

    protected function fire(): int
    {
        $storagePath = $this->paths->storage.'/debugbar';

        if (! is_dir($storagePath)) {
            $this->info('No debugbar storage directory found.');

            return 0;
        }

        $files = glob($storagePath.'/*.json') ?: [];
        $count = count($files);

        foreach ($files as $file) {
            unlink($file);
        }

        $this->info("Cleared {$count} debugbar request files.");

        return 0;
    }
}
