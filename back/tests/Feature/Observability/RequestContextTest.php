<?php

namespace Tests\Feature\Observability;

use App\Http\Middleware\AssignRequestContext;
use App\Support\SafeExceptionContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

/**
 * AUTHORED — MAC EXECUTION PENDING.
 *
 * These tests protect the external correlation-header contract and the
 * deliberately minimal exception projection. They are not Windows-executable
 * under the backend runtime restrictions for this task.
 */
class RequestContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_canonical_request_id_is_normalized_and_propagated_to_the_api_response(): void
    {
        $requestId = '018f0e38-7e70-7b7e-8d1f-0123456789ab';

        $this->getJson('/api/health', ['X-Request-Id' => strtoupper($requestId)])
            ->assertOk()
            ->assertHeader('X-Request-Id', $requestId);
    }

    public function test_middleware_stores_the_normalized_request_id_in_the_request_attribute(): void
    {
        $requestId = '018f0e38-7e70-7b7e-8d1f-0123456789ab';
        $request = Request::create('/api/request-context-test', 'GET', [], [], [], [
            'HTTP_X_REQUEST_ID' => strtoupper($requestId),
        ]);

        $response = app(AssignRequestContext::class)->handle(
            $request,
            static fn (Request $handledRequest) => response()->json([
                'request_id' => $handledRequest->attributes->get('request_id'),
            ]),
        );

        $this->assertSame($requestId, $request->attributes->get('request_id'));
        $this->assertSame($requestId, $response->headers->get('X-Request-Id'));
    }

    public function test_invalid_or_oversized_request_id_is_replaced_with_a_uuid7(): void
    {
        foreach (['not-a-uuid', str_repeat('a', 37)] as $untrustedRequestId) {
            $response = $this->getJson('/api/health', ['X-Request-Id' => $untrustedRequestId])
                ->assertOk();

            $effectiveRequestId = (string) $response->headers->get('X-Request-Id');

            $this->assertNotSame($untrustedRequestId, $effectiveRequestId);
            $this->assertMatchesRegularExpression(
                '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
                $effectiveRequestId,
            );
        }
    }

    public function test_invalid_request_id_is_attached_to_rendered_api_validation_error(): void
    {
        $untrustedRequestId = 'INVALID_ERROR_RESPONSE_REQUEST_ID';

        $response = $this->postJson('/api/auth/login', [
            'email' => 'not-an-email-address',
            'password' => 'irrelevant-password',
        ], ['X-Request-Id' => $untrustedRequestId])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $effectiveRequestId = (string) $response->headers->get('X-Request-Id');

        $this->assertNotSame($untrustedRequestId, $effectiveRequestId);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $effectiveRequestId,
        );
    }

    public function test_oversized_request_id_is_not_emitted_by_public_graph_log_context(): void
    {
        $untrustedRequestId = 'OVERSIZED_PUBLIC_GRAPH_REQUEST_ID_'.str_repeat('x', 128);
        $publicGraphContext = null;

        Log::listen(function (MessageLogged $event) use (&$publicGraphContext): void {
            if ($event->message === 'PublicGraph bootstrap request') {
                $publicGraphContext = $event->context;
            }
        });

        $this->getJson('/api/public/graph/bootstrap', ['X-Request-Id' => $untrustedRequestId])
            ->assertOk();

        $this->assertNotNull($publicGraphContext);
        $this->assertNotSame($untrustedRequestId, $publicGraphContext['request_id']);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $publicGraphContext['request_id'],
        );
    }

    public function test_safe_exception_context_excludes_nested_exception_message_and_trace_secrets(): void
    {
        $secret = 'NESTED_EXCEPTION_SECRET_SENTINEL';
        $exception = new RuntimeException('outer exception', 41, new RuntimeException($secret, 42));

        $context = SafeExceptionContext::from($exception);

        $this->assertSame(
            [
                'exception_class' => RuntimeException::class,
                'exception_code' => 41,
                'fingerprint' => hash('sha256', RuntimeException::class.'|41|'.RuntimeException::class),
                'previous_exception_class' => RuntimeException::class,
            ],
            $context,
        );
        $this->assertStringNotContainsString($secret, json_encode($context) ?: '');
    }
}
