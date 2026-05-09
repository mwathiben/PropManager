<?php

declare(strict_types=1);

namespace App\Services\Gateways;

use App\Contracts\PaymentGatewayInterface;
use App\Exceptions\Integration\PaymentGatewayUnreachableException;
use App\Services\PaystackService;
use App\ValueObjects\Payment\Money;
use App\ValueObjects\Payment\PaymentRequest;
use App\ValueObjects\Payment\PaymentResult;
use Illuminate\Http\Request;

class PaystackGateway implements PaymentGatewayInterface
{
    public function __construct(
        protected PaystackService $service,
    ) {}

    public function getIdentifier(): string
    {
        return 'paystack';
    }

    public function isConfigured(): bool
    {
        return $this->service->isConfigured();
    }

    public function initializePayment(PaymentRequest $request): PaymentResult
    {
        $data = [
            'email' => $request->email,
            'amount' => $request->amount->toFloat(),
            'currency' => $request->amount->currency,
            'reference' => $request->reference,
            'callback_url' => $request->callbackUrl ?? route('payments.callback'),
            'metadata' => $request->metadata,
        ];

        try {
            $response = $this->service->initializeTransaction($data);
        } catch (PaymentGatewayUnreachableException $e) {
            return PaymentResult::failed(
                error: $e->getMessage(),
                errorCode: $e->getErrorCode(),
                reference: $request->reference,
            );
        }

        if ($response === null) {
            return PaymentResult::failed(
                error: 'Failed to initialize Paystack transaction',
                reference: $request->reference,
            );
        }

        if (! ($response['status'] ?? false)) {
            return PaymentResult::failed(
                error: $response['message'] ?? 'Unknown error',
                reference: $request->reference,
                rawResponse: $response,
            );
        }

        $responseData = $response['data'] ?? [];

        return PaymentResult::initialized(
            reference: $responseData['reference'] ?? $request->reference,
            authorizationUrl: $responseData['authorization_url'] ?? '',
            accessCode: $responseData['access_code'] ?? null,
            rawResponse: $response,
        );
    }

    public function verifyPayment(string $reference): PaymentResult
    {
        $response = $this->service->verifyTransaction($reference);

        if ($response === null) {
            return PaymentResult::failed(
                error: 'Failed to verify Paystack transaction',
                reference: $reference,
            );
        }

        if (! ($response['status'] ?? false)) {
            return PaymentResult::failed(
                error: $response['message'] ?? 'Unknown error',
                reference: $reference,
                rawResponse: $response,
            );
        }

        $data = $response['data'] ?? [];
        $status = $data['status'] ?? 'failed';
        $amountKobo = $data['amount'] ?? 0;

        return PaymentResult::verified(
            reference: $data['reference'] ?? $reference,
            status: $status,
            amount: Money::fromSmallestUnit($amountKobo, $data['currency'] ?? 'NGN'),
            transactionId: isset($data['id']) ? (string) $data['id'] : null,
            rawResponse: $response,
        );
    }

    public function refundPayment(string $reference, ?Money $amount = null): PaymentResult
    {
        $refundAmount = $amount?->toFloat();
        $response = $this->service->refundTransaction($reference, $refundAmount, $amount?->currency ?? 'KES');

        if ($response === null) {
            return PaymentResult::failed(
                error: 'Failed to process Paystack refund',
                reference: $reference,
            );
        }

        $refundedAmount = $response['amount'] ?? ($amount?->toSmallestUnit() ?? 0);

        return PaymentResult::refunded(
            reference: $response['transaction']['reference'] ?? $reference,
            amount: Money::fromSmallestUnit($refundedAmount, $response['currency'] ?? $amount?->currency ?? 'KES'),
            transactionId: isset($response['id']) ? (string) $response['id'] : null,
            rawResponse: $response,
        );
    }

    public function validateWebhook(Request $request): bool
    {
        $payload = $request->getContent();
        $signature = $request->header('X-Paystack-Signature', '');

        return $this->service->verifyWebhookSignature($payload, $signature);
    }

    public function getPublicKey(): ?string
    {
        return $this->service->getPublicKey();
    }

    public function generateReference(string $prefix = 'PAY'): string
    {
        return PaystackService::generateReference($prefix);
    }

    /**
     * Get the underlying service for gateway-specific operations.
     */
    public function getService(): PaystackService
    {
        return $this->service;
    }
}
