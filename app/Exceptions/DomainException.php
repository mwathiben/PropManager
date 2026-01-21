<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

abstract class DomainException extends Exception
{
    protected string $errorCode;

    protected array $context = [];

    protected int $statusCode = 400;

    public function __construct(
        string $message,
        string $errorCode,
        array $context = [],
        int $statusCode = 400,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);

        $this->errorCode = $errorCode;
        $this->context = $context;
        $this->statusCode = $statusCode;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function render(Request $request): ?JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'error' => $this->errorCode,
                'message' => $this->getMessage(),
                'context' => $this->context,
            ], $this->statusCode);
        }

        return null;
    }

    public function report(): void
    {
        Log::error($this->getMessage(), [
            'error_code' => $this->errorCode,
            'exception' => static::class,
            'context' => $this->context,
        ]);
    }
}
