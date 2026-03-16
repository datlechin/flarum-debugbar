<?php

namespace Datlechin\FlarumDebugbar\Middleware;

use Datlechin\FlarumDebugbar\Collector\ApiCollector;
use Datlechin\FlarumDebugbar\Collector\AuthCollector;
use Datlechin\FlarumDebugbar\Collector\RouteCollector;
use Datlechin\FlarumDebugbar\DebugBarFactory;
use DebugBar\DebugBar;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ApiDebugBarMiddleware implements MiddlewareInterface
{
    protected ?DebugBar $debugbar = null;

    /**
     * Track whether the forum middleware is already active.
     * If so, this is an internal API call and we should not send headers.
     */
    private static bool $forumRequestActive = false;

    public static function setForumRequestActive(bool $active): void
    {
        self::$forumRequestActive = $active;
    }

    public function __construct(
        protected DebugBarFactory $factory,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->debugbar = $this->factory->create();

        if (! $this->debugbar) {
            return $handler->handle($request);
        }

        $path = $request->getUri()->getPath();
        $method = $request->getMethod();
        $measureId = 'api.'.md5($method.$path.microtime());

        $this->debugbar['messages']->addMessage("API: {$method} {$path}");
        $this->debugbar['time']->startMeasure($measureId, "API {$method} {$path}");

        $response = $handler->handle($request);

        try { $this->debugbar['time']->stopMeasure($measureId); } catch (\Throwable) {}

        // Only send debug headers for external AJAX requests, not internal API calls
        if (self::$forumRequestActive) {
            return $response;
        }

        // Pass request to request-aware collectors for standalone API requests
        $this->setRequestOnCollectors($request);

        return $this->addDebugHeaders($response);
    }

    protected function setRequestOnCollectors(ServerRequestInterface $request): void
    {
        if ($this->debugbar->hasCollector('route')) {
            /** @var RouteCollector $collector */
            $collector = $this->debugbar->getCollector('route');
            $collector->setRequest($request);
        }

        if ($this->debugbar->hasCollector('auth')) {
            /** @var AuthCollector $collector */
            $collector = $this->debugbar->getCollector('auth');
            $collector->setRequest($request);
        }

        if ($this->debugbar->hasCollector('api')) {
            /** @var ApiCollector $collector */
            $collector = $this->debugbar->getCollector('api');
            $collector->setRequest($request);
        }
    }

    protected function addDebugHeaders(ResponseInterface $response): ResponseInterface
    {
        try {
            $headers = $this->debugbar->getDataAsHeaders('phpdebugbar', 4096, 250000);

            foreach ($headers as $name => $value) {
                $response = $response->withHeader($name, $value);
            }
        } catch (\Throwable) {
        }

        return $response;
    }
}
