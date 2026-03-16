<?php

namespace Datlechin\FlarumDebugbar;

use DebugBar\DebugBar;

/**
 * Facade for extension developers to log messages and measure performance.
 *
 * Usage:
 *   use Datlechin\FlarumDebugbar\DebugbarHelper;
 *
 *   DebugbarHelper::info('Loading posts');
 *   DebugbarHelper::warning('Deprecated method called');
 *   DebugbarHelper::error('Something went wrong');
 *   DebugbarHelper::startMeasure('my-operation', 'My Heavy Operation');
 *   // ... do work ...
 *   DebugbarHelper::stopMeasure('my-operation');
 */
class DebugbarHelper
{
    protected static ?DebugBar $debugbar = null;

    public static function setDebugBar(?DebugBar $debugbar): void
    {
        static::$debugbar = $debugbar;
    }

    public static function getDebugBar(): ?DebugBar
    {
        return static::$debugbar;
    }

    public static function isEnabled(): bool
    {
        return static::$debugbar !== null;
    }

    public static function info(string $message): void
    {
        static::message($message, 'info');
    }

    public static function warning(string $message): void
    {
        static::message($message, 'warning');
    }

    public static function error(string $message): void
    {
        static::message($message, 'error');
    }

    public static function debug(string $message): void
    {
        static::message($message, 'debug');
    }

    public static function message(string $message, string $label = 'info'): void
    {
        if (! static::$debugbar?->hasCollector('messages')) {
            return;
        }

        static::$debugbar['messages']->addMessage($message, $label);
    }

    public static function startMeasure(string $name, ?string $label = null): void
    {
        if (! static::$debugbar?->hasCollector('time')) {
            return;
        }

        try {
            static::$debugbar['time']->startMeasure($name, $label ?? $name);
        } catch (\Throwable) {
        }
    }

    public static function stopMeasure(string $name): void
    {
        if (! static::$debugbar?->hasCollector('time')) {
            return;
        }

        try {
            static::$debugbar['time']->stopMeasure($name);
        } catch (\Throwable) {
        }
    }

    public static function addException(\Throwable $e): void
    {
        if (! static::$debugbar?->hasCollector('exceptions')) {
            return;
        }

        static::$debugbar['exceptions']->addThrowable($e);
    }
}
