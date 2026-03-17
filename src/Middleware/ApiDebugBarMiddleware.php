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

        /** @var \DebugBar\DataCollector\MessagesCollector $messages */
        $messages = $this->debugbar['messages'];
        $messages->addMessage("API: {$method} {$path}");

        /** @var \DebugBar\DataCollector\TimeDataCollector $time */
        $time = $this->debugbar['time'];
        $time->startMeasure($measureId, "API {$method} {$path}");

        $response = $handler->handle($request);

        try { $time->stopMeasure($measureId); } catch (\Throwable) {}

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
            // If storage is available, collect and store the data so the JS
            // open handler can retrieve it. Only send a small request-id header
            // instead of the full data — avoids overflowing nginx buffer limits.
            if ($this->debugbar->getStorage()) {
                $this->debugbar->collect();
                $this->debugbar->getStorage()->save(
                    $this->debugbar->getCurrentRequestId(),
                    $this->debugbar->getData()
                );

                $response = $response->withHeader(
                    'phpdebugbar-id',
                    $this->debugbar->getCurrentRequestId()
                );

                return $response;
            }

            // Fallback: no storage, send data in headers with a safe limit
            $headers = $this->debugbar->getDataAsHeaders('phpdebugbar', 4096, 4096);

            foreach ($headers as $name => $value) {
                $response = $response->withHeader($name, $value);
            }
        } catch (\Throwable) {
        }

        return $response;
    }
}
