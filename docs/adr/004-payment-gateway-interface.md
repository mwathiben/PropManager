# ADR-004: Abstract Payment Gateways Behind Interface

## Status

Accepted (DBP-034)

## Context

PropManager supports multiple payment gateways:

1. **Paystack** - Card payments (Nigeria, Ghana)
2. **M-Pesa** - Mobile money (Kenya)
3. **Future**: IntaSend, bank transfers, etc.

Each gateway has different APIs, authentication, and response formats. Without abstraction:
- Controllers directly call gateway-specific services
- Adding new gateways requires modifying multiple controllers
- Testing requires mocking specific gateway implementations

## Decision

All payment gateways implement `PaymentGatewayInterface`:

```php
interface PaymentGatewayInterface
{
    public function isConfigured(): bool;
    public function initializePayment(PaymentRequest $request): PaymentResult;
    public function verifyPayment(string $reference): PaymentResult;
    public function refundPayment(string $transactionId, Money $amount): PaymentResult;
    public function validateWebhook(Request $request): bool;
    public function getPublicKey(): ?string;
    public function generateReference(string $prefix = 'PAY'): string;
}
```

Adapters wrap existing services:
- `PaystackGateway` wraps `PaystackService`
- `MpesaGateway` wraps `MpesaService`

`PaymentGatewayManager` handles gateway selection:

```php
// In controllers
$gateway = $this->gatewayManager->gateway('paystack');
$result = $gateway->initializePayment($request);

// Or use default gateway
$gateway = $this->gatewayManager->defaultGateway();
```

## Consequences

### Positive

- **Pluggable gateways**: New gateways just implement interface
- **Testable**: Mock the interface, not specific gateway
- **Consistent API**: All gateways use same method signatures
- **Type safety**: Value objects (Money, PaymentRequest) prevent errors
- **Gateway-agnostic controllers**: Controllers don't know which gateway

### Negative

- Additional abstraction layer
- Gateway-specific features need custom methods on adapters
- Learning curve for new developers

### Neutral

- Adapters preserve all existing gateway-specific methods
- Value objects (Money, PaymentRequest, PaymentResult) for type safety
- Manager registered as singleton in service container

## References

- DBP-034: Create PaymentGateway Interface and Adapters
- `laravelports-and-adapters` skill
- `laravelinterfaces-and-di` skill
