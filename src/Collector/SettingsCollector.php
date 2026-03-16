<?php

namespace Datlechin\FlarumDebugbar\Collector;

use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\Renderable;
use Flarum\Settings\SettingsRepositoryInterface;

class SettingsCollector extends DataCollector implements Renderable
{
    public function __construct(
        protected SettingsRepositoryInterface $settings,
    ) {
    }

    public function collect(): array
    {
        $all = $this->settings->all();

        ksort($all);

        // Group by prefix (extension namespace)
        $grouped = [];

        foreach ($all as $key => $value) {
            $parts = explode('.', $key, 2);

            if (count($parts) === 2) {
                $group = $parts[0];
            } else {
                $group = '_core';
            }

            $grouped[$group][$key] = $this->truncateValue($value);
        }

        ksort($grouped);

        // Flatten for display
        $result = [];

        foreach ($grouped as $group => $settings) {
            foreach ($settings as $key => $value) {
                $result["[{$group}] {$key}"] = $this->maskSensitive($key, $value);
            }
        }

        return $result;
    }

    protected function maskSensitive(string $key, string $value): string
    {
        if ($value === '') {
            return $value;
        }

        $patterns = ['password', 'secret', 'key', 'token', 'smtp'];

        foreach ($patterns as $pattern) {
            if (stripos($key, $pattern) !== false) {
                return '********';
            }
        }

        return $value;
    }

    protected function truncateValue(mixed $value): string
    {
        $str = (string) $value;

        if (strlen($str) > 200) {
            return substr($str, 0, 200).'... ('.strlen($str).' chars)';
        }

        return $str;
    }

    public function getName(): string
    {
        return 'settings';
    }

    public function getWidgets(): array
    {
        return [
            'settings' => [
                'icon' => 'adjustments',
                'title' => 'Settings',
                'widget' => 'PhpDebugBar.Widgets.VariableListWidget',
                'map' => 'settings',
                'default' => '{}',
            ],
        ];
    }
}
