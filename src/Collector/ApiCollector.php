<?php

namespace Datlechin\FlarumDebugbar\Collector;

use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\Renderable;
use Psr\Http\Message\ServerRequestInterface;

class ApiCollector extends DataCollector implements Renderable
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

        $data = [
            'method' => $this->request->getMethod(),
            'uri' => (string) $this->request->getUri(),
            'route' => $this->request->getAttribute('routeName', 'unknown'),
        ];

        // Extract handler info
        $handler = $this->request->getAttribute('routeHandler');
        $data['handler'] = $this->resolveHandlerName($handler);

        // Route parameters
        $params = $this->request->getAttribute('routeParameters', []);

        if (! empty($params)) {
            $data['parameters'] = json_encode($params, JSON_PRETTY_PRINT);
        }

        // Query params (filters, includes, sort, pagination)
        $query = $this->request->getQueryParams();

        if (isset($query['include'])) {
            $data['includes'] = $query['include'];
        }

        if (isset($query['filter'])) {
            $data['filters'] = json_encode($query['filter'], JSON_PRETTY_PRINT);
        }

        if (isset($query['sort'])) {
            $data['sort'] = $query['sort'];
        }

        if (isset($query['page'])) {
            $data['pagination'] = json_encode($query['page'], JSON_PRETTY_PRINT);
        }

        if (isset($query['fields'])) {
            $data['fields'] = json_encode($query['fields'], JSON_PRETTY_PRINT);
        }

        // Content type of request body
        $contentType = $this->request->getHeaderLine('Content-Type');

        if ($contentType) {
            $data['content_type'] = $contentType;
        }

        return $data;
    }

    protected function resolveHandlerName(mixed $handler): string
    {
        if (is_string($handler)) {
            return $handler;
        }

        if ($handler instanceof \Closure) {
            try {
                $ref = new \ReflectionFunction($handler);
                $uses = $ref->getStaticVariables();

                // toApiResource(): has $resourceClass + $endpointName
                if (isset($uses['resourceClass'])) {
                    $name = class_basename($uses['resourceClass']);

                    return isset($uses['endpointName'])
                        ? "{$name}::{$uses['endpointName']}"
                        : $name;
                }

                // toController(): has $controller
                if (isset($uses['controller'])) {
                    $ctrl = $uses['controller'];

                    if (is_string($ctrl)) {
                        return $ctrl;
                    }

                    if (is_object($ctrl)) {
                        return $ctrl::class;
                    }
                }
            } catch (\Throwable) {
            }

            return 'Closure';
        }

        if (is_object($handler)) {
            return $handler::class;
        }

        return 'unknown';
    }

    public function getName(): string
    {
        return 'api';
    }

    public function getWidgets(): array
    {
        return [
            'api' => [
                'icon' => 'arrows-left-right',
                'title' => 'API',
                'widget' => 'PhpDebugBar.Widgets.VariableListWidget',
                'map' => 'api',
                'default' => '{}',
            ],
        ];
    }
}
