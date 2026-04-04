<?php

declare(strict_types=1);

namespace Didasto\Apilot\Tests\Feature;

use Didasto\Apilot\Routing\CrudRouteRegistrar;
use Didasto\Apilot\Tests\Fixtures\Controllers\PostController as PostControllerFixture;
use Didasto\Apilot\Tests\Fixtures\Models\Post;
use Didasto\Apilot\Tests\TestCase;

class ForceJsonMiddlewareTest extends TestCase
{
    public function registerTestRoutes(): void
    {
        // Register via CrudRouteRegistrar so the auto-middleware feature applies
        CrudRouteRegistrar::resource('fjm-posts', PostControllerFixture::class)->register();
    }

    // =========================================================================
    // Content-Type response header
    // =========================================================================

    public function testResponseHasJsonContentTypeHeader(): void
    {
        Post::factory()->create(['title' => 'Test Post']);

        $response = $this->getJson('api/fjm-posts');

        $response->assertStatus(200);
        $this->assertStringContainsString(
            'application/json',
            $response->headers->get('Content-Type'),
        );
    }

    // =========================================================================
    // Accept header forced on request
    // =========================================================================

    public function testRequestAcceptHeaderIsSetToJson(): void
    {
        Post::factory()->create(['title' => 'Test Post']);

        // Make a plain GET request without Accept header
        $response = $this->get('api/fjm-posts');

        // Middleware should force JSON response (not HTML)
        $response->assertStatus(200);
        $this->assertStringContainsString(
            'application/json',
            $response->headers->get('Content-Type'),
        );
    }

    public function testValidationErrorReturnsJsonNotHtml(): void
    {
        // POST with missing required 'title' field, no Accept header
        $response = $this->post('api/fjm-posts', ['body' => 'No title']);

        // Middleware forces Accept: application/json → FormRequest returns 422 JSON, not 302 redirect
        $response->assertStatus(422);
        $data = $response->json();
        $this->assertArrayHasKey('message', $data);
        $this->assertArrayHasKey('errors', $data);
    }

    // =========================================================================
    // Doc route
    // =========================================================================

    public function testDocRouteHasJsonContentType(): void
    {
        $response = $this->get('api/doc');

        $response->assertStatus(200);
        $this->assertStringContainsString(
            'application/json',
            $response->headers->get('Content-Type'),
        );
    }

    // =========================================================================
    // force_json config
    // =========================================================================

    public function testForceJsonEnabledByDefault(): void
    {
        // Default config has force_json = true
        $this->assertTrue(config('apilot.force_json', true));

        // Routes registered in registerTestRoutes() have apilot.json middleware
        // → POST without Accept header returns 422 JSON, not redirect
        $response = $this->post('api/fjm-posts', ['body' => 'No title']);

        $response->assertStatus(422);
    }

    public function testForceJsonCanBeDisabledViaConfig(): void
    {
        config()->set('apilot.force_json', false);

        // Register a new route WITHOUT apilot.json auto-applied
        CrudRouteRegistrar::resource('disabled-posts', PostControllerFixture::class)->register();

        // POST without Accept header → without middleware, FormRequest redirects (302) on failure
        $response = $this->post('api/disabled-posts', ['body' => 'No title']);

        // Without apilot.json forcing Accept: application/json, validation error causes redirect
        $response->assertStatus(302);
    }

    // =========================================================================
    // Custom middleware is still applied alongside apilot.json
    // =========================================================================

    public function testCustomMiddlewareStillApplied(): void
    {
        // Register route with auth middleware + force_json = true
        CrudRouteRegistrar::resource('auth-posts', PostControllerFixture::class)
            ->middleware(['auth'])
            ->register();

        // apilot.json runs first → sets Accept: application/json
        // auth middleware → throws AuthenticationException → 401 JSON (because wantsJson=true)
        $response = $this->get('api/auth-posts');

        // auth blocked the request
        $response->assertStatus(401);

        // apilot.json set the Content-Type header on the response
        $this->assertStringContainsString(
            'application/json',
            $response->headers->get('Content-Type'),
        );
    }
}
