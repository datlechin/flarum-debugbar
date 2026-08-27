<?php

/*
 * This file is part of datlechin/flarum-debugbar.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\FlarumDebugbar;

use Flarum\Foundation\ErrorHandling\Reporter;

/**
 * Puts the exception behind a failed request into the Messages panel.
 *
 * Flarum's error handler sits *below* this extension's outermost middleware,
 * so by the time a failure reaches that middleware it is no longer an
 * exception — it has already been caught and formatted into a 500 response.
 * The panel would therefore be empty for exactly the request anyone would open
 * it to look at.
 *
 * Registering as a reporter is how Flarum offers this: `HandleErrors` calls
 * every reporter for errors it does not recognise, which is the same set a log
 * file would receive. Known errors — a 404, a permission failure, a validation
 * error — are deliberately not reported, and are not worth a stack trace here
 * either.
 */
class ReportToDebugbar implements Reporter
{
    public function __construct(
        protected Debugbar $debugbar,
    ) {
    }

    public function report(\Throwable $error): void
    {
        $this->debugbar->exception($error);
    }
}
