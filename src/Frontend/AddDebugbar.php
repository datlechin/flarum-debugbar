<?php

/*
 * This file is part of datlechin/flarum-debugbar.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\FlarumDebugbar\Frontend;

use Datlechin\FlarumDebugbar\Debugbar;
use Datlechin\FlarumDebugbar\Support\Settings;
use Flarum\Frontend\Document;
use Flarum\Http\RequestUtil;
use Flarum\Settings\SettingsRepositoryInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Tells the page that a debug bar is available, and where this request's
 * profile will be found.
 *
 * Nothing but a few bytes of payload is added here: the bar's markup is built
 * by the frontend, from data fetched over the API. The alternative — writing
 * the bar's HTML into the document — is what forces string surgery on the
 * response body, and it is why the old implementation needed its assets
 * symlinked into `public/` to be reachable at all.
 *
 * The absence of this payload is what switches the frontend off, so a forum
 * that is not in debug mode, or a visitor who is not an administrator, never
 * sees the bar even though the code for it is in the bundle.
 */
class AddDebugbar
{
    public function __construct(
        protected Debugbar $debugbar,
        protected SettingsRepositoryInterface $settings,
    ) {
    }

    public function __invoke(Document $document, ServerRequestInterface $request): void
    {
        if (! $this->debugbar->isEnabled() || ! RequestUtil::getActor($request)->isAdmin()) {
            return;
        }

        $document->payload['debugbar'] = [
            // The profile for *this* page. It is still being written as the
            // document renders, so the frontend fetches it a moment later
            // rather than receiving it inline — which also means the figures
            // it shows include the cost of rendering, not just of getting
            // this far.
            'requestId' => $this->debugbar->id(),
            'openByDefault' => (bool) $this->settings->get(Settings::OPEN_BY_DEFAULT, false),
        ];
    }
}
