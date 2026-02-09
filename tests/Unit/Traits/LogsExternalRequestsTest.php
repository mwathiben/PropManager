<?php

namespace Tests\Unit\Traits;

use App\Traits\LogsExternalRequests;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class LogsExternalRequestsTest extends TestCase
{
    use LogsExternalRequests;

    public function test_timed_http_request_logs_info_on_success(): void
    {
        $mockResponse = $this->createMock(Response::class);
        $mockResponse->method('status')->willReturn(200);

        Log::shouldReceive('info')
            ->once()
            ->withArgs(function ($message, $context) {
                return $message === 'External API call completed'
                    && $context['provider'] === 'paystack'
                    && $context['endpoint'] === '/transaction/initialize'
                    && $context['duration_ms'] >= 0
                    && $context['status_code'] === 200;
            });

        $result = $this->timedHttpRequest('paystack', '/transaction/initialize', fn () => $mockResponse);

        $this->assertSame($mockResponse, $result);
    }

    public function test_timed_http_request_logs_warning_for_slow_calls(): void
    {
        $mockResponse = $this->createMock(Response::class);
        $mockResponse->method('status')->willReturn(200);

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function ($message, $context) {
                return $message === 'External API call completed'
                    && $context['provider'] === 'mpesa'
                    && $context['duration_ms'] > 5000;
            });

        $this->timedHttpRequest('mpesa', '/mpesa/stkpush', function () use ($mockResponse) {
            usleep(5100 * 1000); // 5.1 seconds

            return $mockResponse;
        });
    }

    public function test_timed_http_request_logs_duration_on_connection_failure(): void
    {
        Log::shouldReceive('info')
            ->once()
            ->withArgs(function ($message, $context) {
                return $message === 'External API call completed'
                    && $context['provider'] === 'intasend'
                    && $context['endpoint'] === '/api/v1/payment/mpesa-stk-push'
                    && $context['status_code'] === 0
                    && $context['duration_ms'] >= 0;
            });

        $this->expectException(ConnectionException::class);

        $this->timedHttpRequest('intasend', '/api/v1/payment/mpesa-stk-push', function () {
            throw new ConnectionException('Connection timed out');
        });
    }

    public function test_timed_http_request_logs_duration_on_general_exception(): void
    {
        Log::shouldReceive('info')
            ->once()
            ->withArgs(function ($message, $context) {
                return $message === 'External API call completed'
                    && $context['provider'] === 'paystack'
                    && $context['status_code'] === 0;
            });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Server error');

        $this->timedHttpRequest('paystack', '/transaction/verify', function () {
            throw new \RuntimeException('Server error');
        });
    }

    public function test_timed_http_request_returns_response_unchanged(): void
    {
        $mockResponse = $this->createMock(Response::class);
        $mockResponse->method('status')->willReturn(401);

        Log::shouldReceive('info')
            ->once()
            ->withArgs(function ($message, $context) {
                return $context['status_code'] === 401;
            });

        $result = $this->timedHttpRequest('paystack', '/subaccount', fn () => $mockResponse);

        $this->assertSame($mockResponse, $result);
    }
}
