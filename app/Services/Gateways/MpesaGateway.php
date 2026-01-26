<?php

declare(strict_types=1);

namespace App\Services\Gateways;

use App\Contracts\PaymentGatewayInterface;
use App\Models\PaymentConfiguration;
use App\Services\MpesaService;
use App\ValueObjects\Payment\Money;
use App\ValueObjects\Payment\PaymentRequest;
use App\ValueObjects\Payment\PaymentResult;
use Illuminate\Http\Request;

class MpesaGateway implements PaymentGatewayInterface
{
    protected ?PaymentConfiguration $config = null;

    public function __construct(
        protected MpesaService $service,
    ) {}

    public function getIdentifier(): string
    {
        return 'mpesa';
    }

    public function isConfigured(): bool
    {
        return $this->service->isConfigured();
    }

    /**
     * Set per-landlord payment configuration.
     */
    public function withConfig(?PaymentConfiguration $config): self
    {
        $clone = clone $this;
        $clone->config = $config;

        return $clone;
    }

    public function initializePayment(PaymentRequest $request): PaymentResult
    {
        if (! $request->phone) {
            return PaymentResult::failed(
                error: 'Phone number is required for M-Pesa payments',
                reference: $request->reference,
            );
        }

        $data = [
            'phone' => $request->phone,
            'amount' => $request->amount->toMpesaAmount(),
            'account_reference' => $request->reference,
            'description' => $request->description ?? 'Payment',
            'callback_url' => $request->callbackUrl ?? route('webhooks.mpesa.stk-callback'),
        ];

        $response = $this->service->initiateSTKPush($data, $this->config);

        if ($response === null) {
            return PaymentResult::failed(
                error: 'Failed to initiate M-Pesa STK Push',
                reference: $request->reference,
            );
        }

        $responseCode = $response['ResponseCode'] ?? null;

        if ($responseCode !== '0') {
            return PaymentResult::failed(
                error: $response['ResponseDescription'] ?? 'M-Pesa request failed',
                errorCode: $responseCode,
                reference: $request->reference,
                rawResponse: $response,
            );
        }

        return PaymentResult::pending(
            reference: $request->reference,
            transactionId: $response['CheckoutRequestID'] ?? null,
            rawResponse: $response,
        );
    }

    public function verifyPayment(string $reference): PaymentResult
    {
        $response = $this->service->querySTKStatus($reference);

        if ($response === null) {
            return PaymentResult::failed(
                error: 'Failed to query M-Pesa STK status',
                reference: $reference,
            );
        }

        $resultCode = $response['ResultCode'] ?? null;

        if ($resultCode === '0' || $resultCode === 0) {
            $callbackMetadata = $response['CallbackMetadata']['Item'] ?? [];
            $amount = $this->extractCallbackValue($callbackMetadata, 'Amount');

            return PaymentResult::verified(
                reference: $reference,
                status: 'success',
                amount: Money::fromFloat((float) ($amount ?? 0), 'KES'),
                transactionId: $this->extractCallbackValue($callbackMetadata, 'MpesaReceiptNumber'),
                rawResponse: $response,
            );
        }

        if ($resultCode === '1032' || $resultCode === 1032) {
            return PaymentResult::failed(
                error: 'Transaction cancelled by user',
                errorCode: (string) $resultCode,
                reference: $reference,
                rawResponse: $response,
            );
        }

        return PaymentResult::pending(
            reference: $reference,
            rawResponse: $response,
        );
    }

    public function refundPayment(string $reference, ?Money $amount = null): PaymentResult
    {
        return PaymentResult::failed(
            error: 'M-Pesa refunds require phone number. Use initiateB2CRefund() instead.',
            reference: $reference,
        );
    }

    /**
     * Initiate B2C refund with phone number (M-Pesa specific).
     */
    public function initiateB2CRefund(string $phone, Money $amount, string $reference, string $remarks = 'Refund'): PaymentResult
    {
        $response = $this->service->initiateB2C(
            phone: $phone,
            amount: $amount->toMpesaAmount(),
            reference: $reference,
            remarks: $remarks,
        );

        if ($response === null) {
            return PaymentResult::failed(
                error: 'Failed to initiate M-Pesa B2C refund',
                reference: $reference,
            );
        }

        $responseCode = $response['ResponseCode'] ?? null;

        if ($responseCode !== '0') {
            return PaymentResult::failed(
                error: $response['ResponseDescription'] ?? 'B2C request failed',
                errorCode: $responseCode,
                reference: $reference,
                rawResponse: $response,
            );
        }

        return PaymentResult::pending(
            reference: $reference,
            transactionId: $response['ConversationID'] ?? null,
            rawResponse: $response,
        );
    }

    public function validateWebhook(Request $request): bool
    {
        return $this->service->validateWebhookIP($request->ip());
    }

    public function getPublicKey(): ?string
    {
        return null;
    }

    public function generateReference(string $prefix = 'MPESA'): string
    {
        return $this->service->generateAccountReference($prefix);
    }

    /**
     * Get the underlying service for gateway-specific operations.
     */
    public function getService(): MpesaService
    {
        return $this->service;
    }

    /**
     * Extract value from M-Pesa callback metadata array.
     *
     * @param  array<array{Name: string, Value: mixed}>  $metadata
     */
    private function extractCallbackValue(array $metadata, string $name): mixed
    {
        foreach ($metadata as $item) {
            if (($item['Name'] ?? '') === $name) {
                return $item['Value'] ?? null;
            }
        }

        return null;
    }
}
