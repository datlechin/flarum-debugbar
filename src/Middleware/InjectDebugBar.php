<?php

namespace Datlechin\FlarumDebugbar\Middleware;

use Datlechin\FlarumDebugbar\Collector\AuthCollector;
use Datlechin\FlarumDebugbar\Collector\RouteCollector;
use Datlechin\FlarumDebugbar\DebugBarFactory;
use DebugBar\DebugBar;
use Flarum\Foundation\Config;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class InjectDebugBar implements MiddlewareInterface
{
    protected ?DebugBar $debugbar = null;

    private const FLARUM_THEME_CSS = <<<'CSS'
div.phpdebugbar,
div.phpdebugbar-openhandler,
div.phpdebugbar-widgets-datasets-panel {
    --debugbar-icon-brand-flarum: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='38.1 0 435.7 512'%3E%3Cpath d='M287.1 349.1V512L77.4 374.8l-8.4-5.5-18.3-12c-3.2-2.1-5.9-4.9-8-8.1z' fill='currentColor' opacity='.7'/%3E%3Cpath d='M48.5 0c-5.7 0-10.4 4.6-10.4 10.4v323.8c0 5.3 1.5 10.5 4.4 15 2.1 3.2 4.8 6 8 8.1l18.3 12 8.4 5.5s-34.8-25.7 7.5-25.7h389.1V0z' fill='currentColor'/%3E%3C/svg%3E");
    --debugbar-icon-brand: var(--debugbar-icon-brand-flarum);

    --flarum-primary: #e7742e;

    --debugbar-font-sans: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Ubuntu, Cantarell, Oxygen, Roboto, Helvetica, Arial, sans-serif;

    --debugbar-background: #fff;
    --debugbar-background-alt: var(--body-bg-light, #f7f7f7);
    --debugbar-text: #333;
    --debugbar-text-muted: var(--muted-color, #888);
    --debugbar-border: var(--control-bg, #e8ecf3);

    --debugbar-header: var(--body-bg-light, #f0f2f7);
    --debugbar-header-text: #555;
    --debugbar-header-border: var(--control-bg, #e0e5ee);
    --debugbar-header-hover: var(--control-bg-shaded, #e4e7ee);

    --debugbar-active: var(--flarum-primary);
    --debugbar-active-text: #fff;

    --debugbar-icons: var(--debugbar-header-text);
    --debugbar-badge: var(--control-bg, #e0e0e0);
    --debugbar-badge-text: #555;

    --debugbar-badge-active: var(--flarum-primary);
    --debugbar-badge-active-text: #fff;

    --debugbar-link: var(--muted-color, #777);
    --debugbar-hover: var(--link-color, #4D698E);

    --debugbar-accent: var(--flarum-primary);
    --debugbar-accent-border: var(--flarum-primary);
}

div.phpdebugbar[data-theme='dark'],
div.phpdebugbar-openhandler[data-theme='dark'],
div.phpdebugbar-widgets-datasets-panel[data-theme='dark'] {
    --debugbar-background: var(--body-bg, #1e2433);
    --debugbar-background-alt: var(--body-bg-shaded, #171c29);
    --debugbar-text: var(--text-color, #ddd);
    --debugbar-text-muted: var(--muted-color, #8a92a5);
    --debugbar-border: var(--control-bg, #2a3040);

    --debugbar-header: var(--body-bg-shaded, #171c29);
    --debugbar-header-text: var(--muted-color-light, #bbb);
    --debugbar-header-border: var(--control-bg, #2a3040);
    --debugbar-header-hover: var(--control-bg-shaded, #252b3a);

    --debugbar-active: var(--flarum-primary);
    --debugbar-active-text: #fff;

    --debugbar-icons: var(--debugbar-header-text);
    --debugbar-badge: var(--control-bg, #2a3040);
    --debugbar-badge-text: var(--muted-color-light, #bbb);

    --debugbar-badge-active: var(--flarum-primary);
    --debugbar-badge-active-text: #fff;

    --debugbar-link: var(--muted-color-light, #aaa);
    --debugbar-hover: var(--link-color, #6b8bb5);

    --debugbar-accent: var(--flarum-primary);
    --debugbar-accent-border: var(--flarum-primary);
}

div.phpdebugbar-resize-handle {
    background-color: var(--flarum-primary) !important;
}

a.phpdebugbar-tab.phpdebugbar-active {
    background: var(--flarum-primary);
    color: #fff !important;
}

a.phpdebugbar-tab.phpdebugbar-active span.phpdebugbar-badge {
    background-color: rgba(255,255,255,0.25);
    color: #fff;
}

a.phpdebugbar-tab span.phpdebugbar-badge {
    border-radius: var(--border-radius, 4px);
}

a.phpdebugbar-restore-btn:after {
    background: var(--flarum-primary) !important;
}

div.phpdebugbar-openhandler {
    border-top: 3px solid var(--flarum-primary);
}
CSS;

    public function __construct(
        protected DebugBarFactory $factory,
        protected Config $config,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->debugbar = $this->factory->create();

        if (! $this->debugbar) {
            return $handler->handle($request);
        }

        /** @var \DebugBar\DataCollector\MessagesCollector $messages */
        $messages = $this->debugbar['messages'];
        $messages->addMessage('Route: '.$request->getUri()->getPath());

        /** @var \DebugBar\DataCollector\TimeDataCollector $time */
        $time = $this->debugbar['time'];

        $time->startMeasure('forum.request', 'Forum Request');
        $time->startMeasure('forum.handler', 'Route Handler');

        // Mark forum request as active so API middleware skips getDataAsHeaders()
        ApiDebugBarMiddleware::setForumRequestActive(true);
        $response = $handler->handle($request);
        ApiDebugBarMiddleware::setForumRequestActive(false);

        try { $time->stopMeasure('forum.handler'); } catch (\Throwable) {}

        $time->startMeasure('forum.debugbar', 'Debugbar Injection');

        // Pass request to request-aware collectors
        $this->setRequestOnCollectors($request);

        $result = $this->injectDebugBar($response);

        try { $time->stopMeasure('forum.debugbar'); } catch (\Throwable) {}
        try { $time->stopMeasure('forum.request'); } catch (\Throwable) {}

        return $result;
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
    }

    protected function injectDebugBar(ResponseInterface $response): ResponseInterface
    {
        $contentType = $response->getHeaderLine('Content-Type');

        if (stripos($contentType, 'text/html') === false) {
            return $response;
        }

        $body = (string) $response->getBody();

        if (stripos($body, '</body>') === false) {
            return $response;
        }

        $renderer = $this->debugbar->getJavascriptRenderer();
        $renderer->setBaseUrl('/assets/debugbar');
        $renderer->setTheme('auto');
        $renderer->setHideEmptyTabs(true);
        $renderer->setBindAjaxHandlerToFetch(true);
        $renderer->setBindAjaxHandlerToXHR(true);
        $renderer->setAjaxHandlerEnableTab(true);

        // Apply Flarum theme (brand icon, colors, dark mode)
        $renderer->addInlineAssets([self::FLARUM_THEME_CSS], [], []);

        if ($this->debugbar->getStorage()) {
            $basePath = rtrim($this->config->url()->getPath(), '/');
            $renderer->setOpenHandlerUrl($basePath . '/debugbar/open');
        }

        $debugbarHead = $renderer->renderHead();
        $debugbarBody = $renderer->render();

        $body = str_ireplace('</head>', $debugbarHead.'</head>', $body);
        $body = str_ireplace('</body>', $debugbarBody.'</body>', $body);

        $stream = new \Laminas\Diactoros\Stream('php://temp', 'wb+');
        $stream->write($body);

        return $response
            ->withBody($stream)
            ->withoutHeader('Content-Length');
    }
}
