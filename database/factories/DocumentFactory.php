<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\Lease;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DocumentFactory extends Factory
{
    protected $model = Document::class;

    public function definition(): array
    {
        $documentType = fake()->randomElement(array_keys(Document::DOCUMENT_TYPES));
        $extension = fake()->randomElement(['pdf', 'jpg', 'png']);
        $fileName = fake()->slug(3).'.'.$extension;

        return [
            'landlord_id' => User::factory()->state(['role' => 'landlord']),
            'documentable_id' => null,
            'documentable_type' => null,
            'title' => Document::DOCUMENT_TYPES[$documentType],
            'file_name' => $fileName,
            'file_path' => fn (array $attrs) => 'documents/'.$attrs['landlord_id'].'/'.fake()->uuid().'/'.$attrs['file_name'],
            'mime_type' => $this->getMimeType($extension),
            'file_size' => fake()->numberBetween(10240, 5242880),
            'document_type' => $documentType,
            'description' => fake()->optional(0.5)->sentence(),
            'uploaded_by' => fn (array $attrs) => $attrs['landlord_id'],
        ];
    }

    private function getMimeType(string $extension): string
    {
        return match ($extension) {
            'pdf' => 'application/pdf',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            default => 'application/octet-stream',
        };
    }

    public function leaseAgreement(): static
    {
        return $this->state([
            'document_type' => 'lease_agreement',
            'title' => 'Lease Agreement',
            'file_name' => 'lease-agreement.pdf',
            'file_path' => fn (array $attrs) => 'documents/'.$attrs['landlord_id'].'/'.fake()->uuid().'/lease-agreement.pdf',
            'mime_type' => 'application/pdf',
        ]);
    }

    public function tenantId(): static
    {
        return $this->state([
            'document_type' => 'tenant_id',
            'title' => 'Tenant ID',
            'file_name' => 'tenant-id.jpg',
            'file_path' => fn (array $attrs) => 'documents/'.$attrs['landlord_id'].'/'.fake()->uuid().'/tenant-id.jpg',
            'mime_type' => 'image/jpeg',
        ]);
    }

    public function tenantPassport(): static
    {
        return $this->state([
            'document_type' => 'tenant_passport',
            'title' => 'Tenant Passport',
            'file_name' => 'passport.jpg',
            'file_path' => fn (array $attrs) => 'documents/'.$attrs['landlord_id'].'/'.fake()->uuid().'/passport.jpg',
            'mime_type' => 'image/jpeg',
        ]);
    }

    public function bankStatement(): static
    {
        return $this->state([
            'document_type' => 'bank_statement',
            'title' => 'Bank Statement',
            'file_name' => 'bank-statement.pdf',
            'file_path' => fn (array $attrs) => 'documents/'.$attrs['landlord_id'].'/'.fake()->uuid().'/bank-statement.pdf',
            'mime_type' => 'application/pdf',
        ]);
    }

    public function payslip(): static
    {
        return $this->state([
            'document_type' => 'payslip',
            'title' => 'Payslip',
            'file_name' => 'payslip.pdf',
            'file_path' => fn (array $attrs) => 'documents/'.$attrs['landlord_id'].'/'.fake()->uuid().'/payslip.pdf',
            'mime_type' => 'application/pdf',
        ]);
    }

    public function referenceLetter(): static
    {
        return $this->state([
            'document_type' => 'reference_letter',
            'title' => 'Reference Letter',
            'file_name' => 'reference-letter.pdf',
            'file_path' => fn (array $attrs) => 'documents/'.$attrs['landlord_id'].'/'.fake()->uuid().'/reference-letter.pdf',
            'mime_type' => 'application/pdf',
        ]);
    }

    public function utilityBill(): static
    {
        return $this->state([
            'document_type' => 'utility_bill',
            'title' => 'Utility Bill',
            'file_name' => 'utility-bill.pdf',
            'file_path' => fn (array $attrs) => 'documents/'.$attrs['landlord_id'].'/'.fake()->uuid().'/utility-bill.pdf',
            'mime_type' => 'application/pdf',
        ]);
    }

    public function other(): static
    {
        return $this->state([
            'document_type' => 'other',
            'title' => 'Other Document',
        ]);
    }

    public function pdf(): static
    {
        $fileName = fake()->slug(3).'.pdf';

        return $this->state([
            'file_name' => $fileName,
            'mime_type' => 'application/pdf',
        ]);
    }

    public function image(): static
    {
        $extension = fake()->randomElement(['jpg', 'png']);
        $fileName = fake()->slug(3).'.'.$extension;

        return $this->state([
            'file_name' => $fileName,
            'mime_type' => $this->getMimeType($extension),
        ]);
    }

    public function forLease(Lease $lease): static
    {
        return $this->state([
            'documentable_id' => $lease->id,
            'documentable_type' => Lease::class,
            'landlord_id' => $lease->landlord_id,
        ]);
    }

    public function forTicket(Ticket $ticket): static
    {
        return $this->state([
            'documentable_id' => $ticket->id,
            'documentable_type' => Ticket::class,
            'landlord_id' => $ticket->landlord_id,
        ]);
    }

    public function forUser(User $user): static
    {
        return $this->state([
            'documentable_id' => $user->id,
            'documentable_type' => User::class,
            'landlord_id' => $user->landlord_id ?? $user->id,
        ]);
    }

    public function uploadedBy(User $user): static
    {
        return $this->state(['uploaded_by' => $user->id]);
    }

    public function forLandlord(User $landlord): static
    {
        return $this->state([
            'landlord_id' => $landlord->id,
            'uploaded_by' => $landlord->id,
        ]);
    }

    public function unattached(): static
    {
        return $this->state([
            'documentable_id' => null,
            'documentable_type' => null,
        ]);
    }
}
