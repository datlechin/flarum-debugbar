<?php

namespace Datlechin\FlarumDebugbar\Console;

use Flarum\Console\AbstractCommand;
use Flarum\Foundation\Paths;

class PublishAssetsCommand extends AbstractCommand
{
    public function __construct(
        protected Paths $paths,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('debugbar:publish')
            ->setDescription('Publish Debugbar assets to the public directory');
    }

    protected function fire(): int
    {
        $source = realpath(
            $this->paths->vendor.'/php-debugbar/php-debugbar/resources'
        );

        if (! $source) {
            $this->error('Debugbar resources not found. Is php-debugbar/php-debugbar installed?');

            return 1;
        }

        $destination = $this->paths->public.'/assets/debugbar';

        // Remove existing symlink or directory
        if (is_link($destination)) {
            unlink($destination);
            $this->info('Removed existing symlink.');
        } elseif (is_dir($destination)) {
            $this->error("Directory exists at {$destination}. Please remove it manually.");

            return 1;
        }

        if (symlink($source, $destination)) {
            $this->info("Debugbar assets published: {$destination} -> {$source}");

            return 0;
        }

        $this->error('Failed to create symlink. Check file permissions.');

        return 1;
    }
}
