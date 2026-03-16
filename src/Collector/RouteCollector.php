<?php

namespace Datlechin\FlarumDebugbar\Collector;

use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\Renderable;
use Psr\Http\Message\ServerRequestInterface;

class RouteCollector extends DataCollector implements Renderable
{
    protected ?ServerRequestInterface $request = null;

    public function setRequest(ServerRequestInterface $request): void
    {
        $this->request = $request;
    }

    public function collect(): array
    {
        if (! $this->request) {
            return [];
        }

        $routeName = $this->request->getAttribute('routeName', 'unknown');
        $routeHandler = $this->request->getAttribute('routeHandler');
        $routeParameters = $this->request->getAttribute('routeParameters', []);

        return [
            'method' => $this->request->getMethod(),
            'uri' => (string) $this->request->getUri()->getPath(),
            'route' => $routeName,
            'handler' => $this->resolveHandlerName($routeHandler),
            'parameters' => ! empty($routeParameters) ? json_encode($routeParameters, JSON_PRETTY_PRINT) : '{}',
        ];
    }

    protected function resolveHandlerName(mixed $handler): string
    {
        if (is_string($handler)) {
            return $handler;
        }

        if ($handler instanceof \Closure) {
            return $this->describeClosureHandler($handler);
        }

        if (is_object($handler)) {
            return $handler::class;
        }

        if (is_array($handler)) {
            return implode('::', array_map(
                fn ($part) => is_object($part) ? $part::class : (string) $part,
                $handler
            ));
        }

        return 'unknown';
    }

    protected function describeClosureHandler(\Closure $closure): string
    {
        try {
            $ref = new \ReflectionFunction($closure);
            $uses = $ref->getStaticVariables();

            // toApiResource(): has $resourceClass + $endpointName
            if (isset($uses['resourceClass'])) {
                $name = class_basename($uses['resourceClass']);

                return isset($uses['endpointName'])
                    ? "{$name}::{$uses['endpointName']}"
                    : $name;
            }

            // toController(): has $controller (string class or closure)
            if (isset($uses['controller'])) {
                $ctrl = $uses['controller'];

                if (is_string($ctrl)) {
                    return $ctrl;
                }

                // toFrontend() passes a closure as $controller — inspect it
                if ($ctrl instanceof \Closure) {
                    $innerRef = new \ReflectionFunction($ctrl);
                    $innerUses = $innerRef->getStaticVariables();

                    if (isset($innerUses['frontend'])) {
                        return 'Frontend('.$innerUses['frontend'].')';
                    }
                }

                if (is_object($ctrl)) {
                    return $ctrl::class;
                }
            }

            // Direct frontend variable (shouldn't happen but just in case)
            if (isset($uses['frontend'])) {
                return 'Frontend('.$uses['frontend'].')';
            }
        } catch (\Throwable) {
        }

        return 'Closure';
    }

    public function getName(): string
    {
        return 'route';
    }

    public function getWidgets(): array
    {
        return [
            'route' => [
                'icon' => 'link',
                'title' => 'Route',
                'widget' => 'PhpDebugBar.Widgets.VariableListWidget',
                'map' => 'route',
                'default' => '{}',
            ],
            'currentroute' => [
                'icon' => 'link',
                'map' => 'route.route',
                'default' => '""',
            ],
        ];
    }
}
