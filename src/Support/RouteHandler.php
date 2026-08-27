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
 * Naming the thing that handled a route.
 *
 * Flarum wraps every route handler in a closure built by
 * {@see \Flarum\Http\RouteHandlerFactory}, so the value on the request is
 * almost never the controller itself. Reporting `Closure` for every route in
 * the forum would make the panel useless, so the closure's bound variables are
 * read back to recover what it was built from.
 */
final class RouteHandler
{
    public static function describe(mixed $handler): string
    {
        return match (true) {
            is_string($handler) => $handler,
            $handler instanceof \Closure => self::describeClosure($handler),
            is_object($handler) => $handler::class,
            is_array($handler) => implode('::', array_map(
                fn (mixed $part) => is_object($part) ? $part::class : Values::stringify($part, 80),
                $handler
            )),
            default => 'unknown',
        };
    }

    private static function describeClosure(\Closure $closure): string
    {
        try {
            $bound = (new \ReflectionFunction($closure))->getStaticVariables();
        } catch (\ReflectionException) {
            return 'Closure';
        }

        // `toApiResource()` closes over the resource class and endpoint name.
        if (isset($bound['resourceClass']) && is_string($bound['resourceClass'])) {
            $resource = class_basename($bound['resourceClass']);

            return isset($bound['endpointName']) && is_string($bound['endpointName'])
                ? $resource.'::'.$bound['endpointName']
                : $resource;
        }

        // `toController()` closes over the controller, which is either its
        // class name or — for `toFrontend()` — another closure that in turn
        // closes over the name of the frontend.
        if (isset($bound['controller'])) {
            $controller = $bound['controller'];

            if (is_string($controller)) {
                return $controller;
            }

            if ($controller instanceof \Closure) {
                return self::describeClosure($controller);
            }

            if (is_object($controller)) {
                return $controller::class;
            }
        }

        if (isset($bound['frontend']) && is_string($bound['frontend'])) {
            return 'Frontend('.$bound['frontend'].')';
        }

        return 'Closure';
    }
}
