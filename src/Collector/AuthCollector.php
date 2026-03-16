<?php

namespace Datlechin\FlarumDebugbar\Collector;

use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\Renderable;
use Flarum\Http\RequestUtil;
use Psr\Http\Message\ServerRequestInterface;

class AuthCollector extends DataCollector implements Renderable
{
    protected ?ServerRequestInterface $request = null;

    public function setRequest(ServerRequestInterface $request): void
    {
        $this->request = $request;
    }

    public function collect(): array
    {
        if (! $this->request) {
            return ['actor' => 'N/A'];
        }

        try {
            $actor = RequestUtil::getActor($this->request);
        } catch (\Throwable) {
            return ['actor' => 'Unable to resolve'];
        }

        $groups = $actor->groups->pluck('name_singular', 'id')->toArray();

        $data = [
            'actor_id' => $actor->id ?? 0,
            'username' => $actor->username ?? 'Guest',
            'is_guest' => $actor->isGuest(),
            'groups' => ! empty($groups) ? implode(', ', $groups) : 'None',
        ];

        // Detect auth method
        $session = $this->request->getAttribute('session');
        $apiKey = $this->request->getAttribute('apiKey');
        $bypassCsrf = $this->request->getAttribute('bypassCsrfToken');

        if ($apiKey) {
            $data['auth_method'] = 'API Key';
        } elseif ($bypassCsrf) {
            $data['auth_method'] = 'Access Token (Header)';
        } elseif ($session) {
            $data['auth_method'] = 'Session';
            $data['session_id'] = substr($session->getId(), 0, 8).'...';
        } else {
            $data['auth_method'] = 'None (Guest)';
        }

        return $data;
    }

    public function getName(): string
    {
        return 'auth';
    }

    public function getWidgets(): array
    {
        return [
            'auth' => [
                'icon' => 'bookmark',
                'title' => 'Auth',
                'widget' => 'PhpDebugBar.Widgets.VariableListWidget',
                'map' => 'auth',
                'default' => '{}',
            ],
            'currentuser' => [
                'icon' => 'bookmark',
                'tooltip' => 'Current User',
                'map' => 'auth.username',
                'default' => '"Guest"',
            ],
        ];
    }
}
