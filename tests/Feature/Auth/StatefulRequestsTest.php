<?php

namespace Tests\Feature\Auth;

use App\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful as SanctumMiddleware;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\FeatureTestCase;

class StatefulRequestsTest extends FeatureTestCase
{
    public function test_requests_without_any_origin_signal_are_not_stateful(): void
    {
        $this->assertFalse(EnsureFrontendRequestsAreStateful::fromFrontend(
            $this->apiRequest(),
        ));
    }

    public function test_same_origin_browser_requests_are_stateful_without_a_referer(): void
    {
        $this->assertTrue(EnsureFrontendRequestsAreStateful::fromFrontend(
            $this->apiRequest(['HTTP_SEC_FETCH_SITE' => 'same-origin']),
        ));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function foreignFetchSites(): array
    {
        return [
            'cross-site' => ['cross-site'],
            'same-site' => ['same-site'],
            'none' => ['none'],
        ];
    }

    #[DataProvider('foreignFetchSites')]
    public function test_other_fetch_sites_are_not_stateful(string $site): void
    {
        $this->assertFalse(EnsureFrontendRequestsAreStateful::fromFrontend(
            $this->apiRequest(['HTTP_SEC_FETCH_SITE' => $site]),
        ));
    }

    public function test_the_original_referer_check_still_applies(): void
    {
        config(['sanctum.stateful' => ['app.example.test']]);

        $this->assertTrue(EnsureFrontendRequestsAreStateful::fromFrontend(
            $this->apiRequest(['HTTP_REFERER' => 'https://app.example.test/dashboard']),
        ));
        $this->assertFalse(EnsureFrontendRequestsAreStateful::fromFrontend(
            $this->apiRequest(['HTTP_REFERER' => 'https://evil.example.test/']),
        ));
    }

    public function test_the_api_group_uses_the_application_middleware(): void
    {
        $api = $this->app['router']->getMiddlewareGroups()['api'];

        $this->assertContains(EnsureFrontendRequestsAreStateful::class, $api);
        $this->assertNotContains(SanctumMiddleware::class, $api);
    }

    /**
     * @param  array<string, string>  $server
     */
    protected function apiRequest(array $server = []): Request
    {
        return Request::create('/api/profile', 'GET', [], [], [], $server);
    }
}
