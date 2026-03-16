<?php

namespace Datlechin\FlarumDebugbar\Http;

use DebugBar\DebugBar;
use DebugBar\OpenHandler;
use Flarum\Http\RequestUtil;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class OpenHandlerController implements RequestHandlerInterface
{
    public function __construct(
        protected DebugBar $debugbar,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if (! RequestUtil::getActor($request)->isAdmin()) {
            return new JsonResponse(['error' => 'Forbidden'], 403);
        }

        $openHandler = new OpenHandler($this->debugbar);

        $queryParams = $request->getQueryParams();

        // OpenHandler expects an array with 'op' key
        $result = $openHandler->handle($queryParams, false, false);

        $data = json_decode($result, true);

        return new JsonResponse($data ?? []);
    }
}
