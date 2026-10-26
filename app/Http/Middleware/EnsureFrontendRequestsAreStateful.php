<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful as SanctumMiddleware;

/**
 * Sanctum's stateful check relies on the Referer or Origin header matching an
 * allow-listed host. Privacy settings, extensions, and an unexpected hostname
 * all make that fail, and the SPA's own API calls then answer 401 even though
 * the user is signed in.
 *
 * Browsers also describe where a request came from with Sec-Fetch-Site. Page
 * scripts cannot set or forge it, and cross-site requests never report
 * "same-origin", so it is a sound second signal for the application's own
 * frontend. CSRF protection still applies to stateful requests.
 */
class EnsureFrontendRequestsAreStateful extends SanctumMiddleware
{
    /**
     * Determine if the given request is from the first-party application frontend.
     *
     * @param  Request  $request
     */
    public static function fromFrontend($request): bool
    {
        if (parent::fromFrontend($request)) {
            return true;
        }

        return $request->headers->get('Sec-Fetch-Site') === 'same-origin';
    }
}
