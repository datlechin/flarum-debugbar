<?php

/*
 * This file is part of datlechin/flarum-debugbar.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\FlarumDebugbar\Collector;

use Datlechin\FlarumDebugbar\Support\RouteHandler;
use Datlechin\FlarumDebugbar\Support\Values;
use Flarum\Http\RequestUtil;
use Flarum\User\User;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * What was asked for, who asked, and what answered.
 *
 * This deliberately covers what three separate panels used to: the route, the
 * actor and the JSON:API parameters are all facets of one question ("what was
 * this request?"), and splitting them meant three tabs of four rows each,
 * two of which were empty on most pages.
 */
class RequestCollector implements CollectorInterface
{
    /**
     * The request as it looked once every middleware had finished with it.
     *
     * PSR-7 requests are immutable, so the route, the session and the
     * authenticated actor are attributes on clones made deep inside the
     * middleware stack — invisible to the outermost middleware holding the
     * original. {@see \Datlechin\FlarumDebugbar\Http\Middleware\CaptureRoute}
     * runs at the bottom of the stack and hands that clone over.
     */
    protected ?ServerRequestInterface $resolved = null;

    public function name(): string
    {
        return 'request';
    }

    public function observe(ServerRequestInterface $request): void
    {
        $this->resolved = $request;
    }

    public function collect(ServerRequestInterface $request, ResponseInterface $response): array
    {
        // A request that failed before reaching the bottom of the stack — a
        // 404, a CSRF rejection — never reached `observe()`. Everything that
        // does not depend on route resolution can still be reported.
        $resolved = $this->resolved ?? $request;

        return [
            'method' => $request->getMethod(),
            'uri' => (string) $request->getUri(),
            'status' => $response->getStatusCode(),
            'route' => $this->route($resolved),
            'actor' => $this->actor($resolved),
            'query' => $this->query($resolved),
            'jsonApi' => $this->jsonApi($resolved),
            'requestHeaders' => $this->headers($request->getHeaders()),
            'responseHeaders' => $this->headers($response->getHeaders()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function route(ServerRequestInterface $request): array
    {
        $parameters = $request->getAttribute('routeParameters');

        return [
            'name' => $request->getAttribute('routeName'),
            'handler' => RouteHandler::describe($request->getAttribute('routeHandler')),
            'parameters' => is_array($parameters) ? array_map(Values::stringify(...), $parameters) : [],
            'internal' => RequestUtil::isInternal($request),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function actor(ServerRequestInterface $request): array
    {
        $actor = $this->resolveActor($request);

        if (! $actor) {
            return ['authentication' => 'unresolved'];
        }

        $data = [
            'id' => $actor->id,
            'username' => $actor->isGuest() ? null : $actor->username,
            'isGuest' => $actor->isGuest(),
            'authentication' => $this->authentication($request),
        ];

        // Both the group list and `isAdmin()` read the groups relation, which
        // loads it if it is not already loaded — issuing a query from inside
        // the collector, and adding it to the very profile that is meant to
        // describe the request. Permission checks load it on nearly every
        // request anyway; where they have not, the panel leaves the rows out
        // rather than causing the query itself.
        if ($actor->relationLoaded('groups')) {
            $data['isAdmin'] = $actor->isAdmin();
            $data['groups'] = $actor->groups->pluck('name_singular')->all();
        }

        return $data;
    }

    protected function resolveActor(ServerRequestInterface $request): ?User
    {
        // Before `InjectActorReference` has run there is no actor reference to
        // read, and `getActor()` would fatal on null rather than return a
        // guest — so the attribute is checked rather than the accessor.
        if (! $request->getAttribute('actorReference')) {
            return null;
        }

        return RequestUtil::getActor($request);
    }

    protected function authentication(ServerRequestInterface $request): string
    {
        return match (true) {
            (bool) $request->getAttribute('apiKey') => 'api key',
            (bool) $request->getAttribute('bypassCsrfToken') => 'access token',
            (bool) $request->getAttribute('session') => 'session',
            default => 'none',
        };
    }

    /**
     * @return array<string, string>
     */
    protected function query(ServerRequestInterface $request): array
    {
        $query = [];

        foreach ($request->getQueryParams() as $key => $value) {
            $query[(string) $key] = Values::redact((string) $key, $value);
        }

        return $query;
    }

    /**
     * The JSON:API parameters, when this was a JSON:API request. Empty
     * otherwise, so the frontend can leave the section out entirely rather
     * than showing five blank rows on every forum page.
     *
     * @return array<string, string>
     */
    protected function jsonApi(ServerRequestInterface $request): array
    {
        $query = $request->getQueryParams();
        $jsonApi = [];

        foreach (['include', 'filter', 'sort', 'page', 'fields'] as $key) {
            if (isset($query[$key])) {
                $jsonApi[$key] = Values::stringify($query[$key]);
            }
        }

        return $jsonApi;
    }

    /**
     * @param array<string, list<string>> $headers
     * @return array<string, string>
     */
    protected function headers(array $headers): array
    {
        $formatted = [];

        foreach ($headers as $name => $values) {
            $formatted[$name] = Values::redact($name, implode(', ', $values));
        }

        ksort($formatted);

        return $formatted;
    }
}
