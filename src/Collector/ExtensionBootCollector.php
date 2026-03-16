<?php

namespace Datlechin\FlarumDebugbar\Collector;

use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\Renderable;
use Flarum\Extension\ExtensionManager;
use Illuminate\Contracts\Container\Container;

class ExtensionBootCollector extends DataCollector implements Renderable
{
    public function __construct(
        protected Container $container,
    ) {
    }

    public function collect(): array
    {
        try {
            $manager = $this->container->make(ExtensionManager::class);
            $enabled = $manager->getEnabledExtensions();

            $data = [];

            foreach ($enabled as $extension) {
                $id = $extension->getId();
                $version = $extension->getVersion();
                $title = $extension->getTitle();

                $data[$id] = $title.($version ? " ({$version})" : '');
            }

            ksort($data);

            return [
                'count' => count($data),
                'extensions' => $data,
            ];
        } catch (\Throwable) {
            return [];
        }
    }

    public function getName(): string
    {
        return 'extensions_boot';
    }

    public function getWidgets(): array
    {
        return [
            'extensions_boot' => [
                'icon' => 'tags',
                'title' => 'Extensions',
                'widget' => 'PhpDebugBar.Widgets.VariableListWidget',
                'map' => 'extensions_boot.extensions',
                'default' => '{}',
            ],
            'extensions_boot:badge' => [
                'map' => 'extensions_boot.count',
                'default' => 0,
            ],
        ];
    }
}
