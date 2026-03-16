<?php

namespace Datlechin\FlarumDebugbar\Collector;

use Datlechin\FlarumDebugbar\Collector\Concerns\ResolvesHandlerName;
use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\Renderable;
use Psr\Http\Message\ServerRequestInterface;

class RouteCollector extends DataCollector implements Renderable
{
    use ResolvesHandlerName;

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
