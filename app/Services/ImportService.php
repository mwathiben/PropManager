<?php

namespace App\Services;

use App\Models\Building;
use App\Models\Import;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Models\WaterReading;
use Illuminate\Support\Facades\Validator;

class ImportService
{
    /**
     * Parse CSV file and return data
     */
    public function parseCSV(string $filePath): array
    {
        $rows = [];
        $handle = fopen($filePath, 'r');

        if ($handle === false) {
            throw new \Exception('Unable to open file');
        }

        // Get headers from first row
        $headers = fgetcsv($handle);

        if ($headers === false) {
            fclose($handle);
            throw new \Exception('Invalid CSV file - no headers found');
        }

        // Clean headers (trim, lowercase, replace spaces with underscores)
        $headers = array_map(fn ($h) => strtolower(trim(str_replace(' ', '_', $h))), $headers);

        // Read remaining rows
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) === count($headers)) {
                $rows[] = array_combine($headers, $row);
            }
        }

        fclose($handle);

        return $rows;
    }

    /**
     * Process import based on type
     */
    public function processImport(Import $import): void
    {
        $import->update([
            'status' => 'processing',
            'started_at' => now(),
        ]);

        try {
            $data = $this->parseCSV(storage_path('app/'.$import->file_path));

            $result = match ($import->type) {
                'tenants' => $this->importTenants($data, $import->landlord_id),
                'leases' => $this->importLeases($data, $import->landlord_id),
                'water_readings' => $this->importWaterReadings($data, $import->landlord_id),
                'invoices' => $this->importInvoices($data, $import->landlord_id),
                'payments' => $this->importPayments($data, $import->landlord_id),
                'units' => $this->importUnits($data, $import->landlord_id),
                default => throw new \Exception('Invalid import type'),
            };

            $import->update([
                'status' => $result['failed_rows'] === 0 ? 'completed' : 'completed',
                'total_rows' => $result['total_rows'],
                'successful_rows' => $result['successful_rows'],
                'failed_rows' => $result['failed_rows'],
                'errors' => $result['errors'],
                'summary' => $result['summary'],
                'completed_at' => now(),
            ]);

        } catch (\Exception $e) {
            $import->update([
                'status' => 'failed',
                'errors' => [['message' => $e->getMessage()]],
                'completed_at' => now(),
            ]);
        }
    }

    /**
     * Import tenants (users with role 'tenant')
     */
    private function importTenants(array $rows, int $landlordId): array
    {
        $successful = 0;
        $failed = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2; // Account for header row

            $validator = Validator::make($row, [
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email',
                'phone' => 'required|string|max:20',
                'national_id' => 'nullable|string|max:20',
            ]);

            if ($validator->fails()) {
                $failed++;
                $errors[] = [
                    'row' => $rowNumber,
                    'data' => $row,
                    'errors' => $validator->errors()->all(),
                ];

                continue;
            }

            try {
                User::create([
                    'name' => $row['name'],
                    'email' => $row['email'],
                    'phone' => $row['phone'],
                    'national_id' => $row['national_id'] ?? null,
                    'role' => 'tenant',
                    'landlord_id' => $landlordId,
                    'password' => bcrypt('password123'), // Default password
                ]);

                $successful++;
            } catch (\Exception $e) {
                $failed++;
                $errors[] = [
                    'row' => $rowNumber,
                    'data' => $row,
                    'errors' => [$e->getMessage()],
                ];
            }
        }

        return [
            'total_rows' => count($rows),
            'successful_rows' => $successful,
            'failed_rows' => $failed,
            'errors' => $errors,
            'summary' => [
                'tenants_created' => $successful,
                'default_password' => 'password123',
            ],
        ];
    }

    /**
     * Import leases
     */
    private function importLeases(array $rows, int $landlordId): array
    {
        $successful = 0;
        $failed = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;

            $validator = Validator::make($row, [
                'unit_number' => 'required|string',
                'tenant_email' => 'required|email',
                'rent_amount' => 'required|numeric|min:0',
                'deposit_amount' => 'required|numeric|min:0',
                'start_date' => 'required|date',
                'end_date' => 'nullable|date|after:start_date',
            ]);

            if ($validator->fails()) {
                $failed++;
                $errors[] = [
                    'row' => $rowNumber,
                    'data' => $row,
                    'errors' => $validator->errors()->all(),
                ];

                continue;
            }

            try {
                // Find unit
                $unit = Unit::where('landlord_id', $landlordId)
                    ->where('unit_number', $row['unit_number'])
                    ->first();

                if (! $unit) {
                    throw new \Exception("Unit {$row['unit_number']} not found");
                }

                // Find tenant
                $tenant = User::where('landlord_id', $landlordId)
                    ->where('email', $row['tenant_email'])
                    ->where('role', 'tenant')
                    ->first();

                if (! $tenant) {
                    throw new \Exception("Tenant with email {$row['tenant_email']} not found");
                }

                // Create lease
                Lease::create([
                    'unit_id' => $unit->id,
                    'tenant_id' => $tenant->id,
                    'landlord_id' => $landlordId,
                    'rent_amount' => $row['rent_amount'],
                    'deposit_amount' => $row['deposit_amount'],
                    'wallet_balance' => 0,
                    'start_date' => $row['start_date'],
                    'end_date' => $row['end_date'] ?? null,
                    'is_active' => true,
                ]);

                // Update unit status
                $unit->update(['status' => 'occupied']);

                $successful++;
            } catch (\Exception $e) {
                $failed++;
                $errors[] = [
                    'row' => $rowNumber,
                    'data' => $row,
                    'errors' => [$e->getMessage()],
                ];
            }
        }

        return [
            'total_rows' => count($rows),
            'successful_rows' => $successful,
            'failed_rows' => $failed,
            'errors' => $errors,
            'summary' => [
                'leases_created' => $successful,
            ],
        ];
    }

    /**
     * Import historical water readings
     */
    private function importWaterReadings(array $rows, int $landlordId): array
    {
        $successful = 0;
        $failed = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;

            $validator = Validator::make($row, [
                'unit_number' => 'required|string',
                'reading_date' => 'required|date',
                'previous_reading' => 'required|numeric|min:0',
                'current_reading' => 'required|numeric|min:0',
                'status' => 'nullable|in:pending,approved,rejected',
            ]);

            if ($validator->fails()) {
                $failed++;
                $errors[] = [
                    'row' => $rowNumber,
                    'data' => $row,
                    'errors' => $validator->errors()->all(),
                ];

                continue;
            }

            try {
                $unit = Unit::where('landlord_id', $landlordId)
                    ->where('unit_number', $row['unit_number'])
                    ->first();

                if (! $unit) {
                    throw new \Exception("Unit {$row['unit_number']} not found");
                }

                // Create water reading (Observer will auto-calculate consumption and cost)
                WaterReading::create([
                    'unit_id' => $unit->id,
                    'landlord_id' => $landlordId,
                    'reading_date' => $row['reading_date'],
                    'previous_reading' => $row['previous_reading'],
                    'current_reading' => $row['current_reading'],
                    'status' => $row['status'] ?? 'approved', // Default to approved for historical data
                    'recorded_by' => auth()->id(),
                ]);

                $successful++;
            } catch (\Exception $e) {
                $failed++;
                $errors[] = [
                    'row' => $rowNumber,
                    'data' => $row,
                    'errors' => [$e->getMessage()],
                ];
            }
        }

        return [
            'total_rows' => count($rows),
            'successful_rows' => $successful,
            'failed_rows' => $failed,
            'errors' => $errors,
            'summary' => [
                'readings_imported' => $successful,
            ],
        ];
    }

    /**
     * Import historical invoices
     */
    private function importInvoices(array $rows, int $landlordId): array
    {
        $successful = 0;
        $failed = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;

            $validator = Validator::make($row, [
                'unit_number' => 'required|string',
                'invoice_number' => 'required|string|unique:invoices,invoice_number',
                'invoice_date' => 'required|date',
                'due_date' => 'required|date',
                'rent_charge' => 'required|numeric|min:0',
                'water_charge' => 'nullable|numeric|min:0',
                'previous_arrears' => 'nullable|numeric|min:0',
                'status' => 'required|in:draft,sent,partial,paid,overdue',
                'paid_amount' => 'nullable|numeric|min:0',
            ]);

            if ($validator->fails()) {
                $failed++;
                $errors[] = [
                    'row' => $rowNumber,
                    'data' => $row,
                    'errors' => $validator->errors()->all(),
                ];

                continue;
            }

            try {
                $unit = Unit::where('landlord_id', $landlordId)
                    ->where('unit_number', $row['unit_number'])
                    ->first();

                if (! $unit) {
                    throw new \Exception("Unit {$row['unit_number']} not found");
                }

                $lease = $unit->activeLease;

                if (! $lease) {
                    throw new \Exception("No active lease found for unit {$row['unit_number']}");
                }

                $waterCharge = $row['water_charge'] ?? 0;
                $previousArrears = $row['previous_arrears'] ?? 0;
                $totalAmount = $row['rent_charge'] + $waterCharge + $previousArrears;

                Invoice::create([
                    'lease_id' => $lease->id,
                    'landlord_id' => $landlordId,
                    'invoice_number' => $row['invoice_number'],
                    'invoice_date' => $row['invoice_date'],
                    'due_date' => $row['due_date'],
                    'rent_charge' => $row['rent_charge'],
                    'water_charge' => $waterCharge,
                    'previous_arrears' => $previousArrears,
                    'total_amount' => $totalAmount,
                    'paid_amount' => $row['paid_amount'] ?? 0,
                    'status' => $row['status'],
                ]);

                $successful++;
            } catch (\Exception $e) {
                $failed++;
                $errors[] = [
                    'row' => $rowNumber,
                    'data' => $row,
                    'errors' => [$e->getMessage()],
                ];
            }
        }

        return [
            'total_rows' => count($rows),
            'successful_rows' => $successful,
            'failed_rows' => $failed,
            'errors' => $errors,
            'summary' => [
                'invoices_imported' => $successful,
            ],
        ];
    }

    /**
     * Import historical payments
     */
    private function importPayments(array $rows, int $landlordId): array
    {
        $successful = 0;
        $failed = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;

            $validator = Validator::make($row, [
                'invoice_number' => 'required|string',
                'payment_date' => 'required|date',
                'amount' => 'required|numeric|min:0',
                'payment_method' => 'required|in:cash,mpesa,bank_transfer,cheque',
                'reference_number' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                $failed++;
                $errors[] = [
                    'row' => $rowNumber,
                    'data' => $row,
                    'errors' => $validator->errors()->all(),
                ];

                continue;
            }

            try {
                $invoice = Invoice::where('landlord_id', $landlordId)
                    ->where('invoice_number', $row['invoice_number'])
                    ->first();

                if (! $invoice) {
                    throw new \Exception("Invoice {$row['invoice_number']} not found");
                }

                Payment::create([
                    'invoice_id' => $invoice->id,
                    'landlord_id' => $landlordId,
                    'amount' => $row['amount'],
                    'payment_date' => $row['payment_date'],
                    'payment_method' => $row['payment_method'],
                    'reference_number' => $row['reference_number'] ?? null,
                    'status' => 'completed',
                ]);

                // Update invoice paid amount
                $invoice->increment('amount_paid', $row['amount']);

                // Update invoice status
                if ($invoice->amount_paid >= $invoice->total_due) {
                    $invoice->update(['status' => 'paid']);
                } elseif ($invoice->amount_paid > 0) {
                    $invoice->update(['status' => 'partial']);
                }

                $successful++;
            } catch (\Exception $e) {
                $failed++;
                $errors[] = [
                    'row' => $rowNumber,
                    'data' => $row,
                    'errors' => [$e->getMessage()],
                ];
            }
        }

        return [
            'total_rows' => count($rows),
            'successful_rows' => $successful,
            'failed_rows' => $failed,
            'errors' => $errors,
            'summary' => [
                'payments_imported' => $successful,
            ],
        ];
    }

    /**
     * Import units (bulk unit creation)
     */
    private function importUnits(array $rows, int $landlordId): array
    {
        $successful = 0;
        $failed = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;

            $validator = Validator::make($row, [
                'building_name' => 'required|string',
                'unit_number' => 'required|string',
                'floor_number' => 'required|integer|min:0',
                'type' => 'nullable|in:studio,1br,2br,3br,bedsitter,single,shop,office',
                'target_rent' => 'required|numeric|min:0',
                'status' => 'nullable|in:vacant,occupied,maintenance',
            ]);

            if ($validator->fails()) {
                $failed++;
                $errors[] = [
                    'row' => $rowNumber,
                    'data' => $row,
                    'errors' => $validator->errors()->all(),
                ];

                continue;
            }

            try {
                $building = Building::where('landlord_id', $landlordId)
                    ->where('name', $row['building_name'])
                    ->first();

                if (! $building) {
                    throw new \Exception("Building {$row['building_name']} not found");
                }

                // Check if unit already exists
                $existingUnit = Unit::where('building_id', $building->id)
                    ->where('unit_number', $row['unit_number'])
                    ->first();

                if ($existingUnit) {
                    throw new \Exception("Unit {$row['unit_number']} already exists in building {$row['building_name']}");
                }

                Unit::create([
                    'building_id' => $building->id,
                    'landlord_id' => $landlordId,
                    'unit_number' => $row['unit_number'],
                    'floor_number' => $row['floor_number'],
                    'type' => $row['type'] ?? 'studio',
                    'target_rent' => $row['target_rent'],
                    'status' => $row['status'] ?? 'vacant',
                    'last_meter_reading' => 0,
                ]);

                $successful++;
            } catch (\Exception $e) {
                $failed++;
                $errors[] = [
                    'row' => $rowNumber,
                    'data' => $row,
                    'errors' => [$e->getMessage()],
                ];
            }
        }

        return [
            'total_rows' => count($rows),
            'successful_rows' => $successful,
            'failed_rows' => $failed,
            'errors' => $errors,
            'summary' => [
                'units_created' => $successful,
            ],
        ];
    }

    /**
     * Get CSV template for import type
     */
    public static function getTemplate(string $type): array
    {
        return match ($type) {
            'tenants' => [
                'headers' => ['Name', 'Email', 'Phone', 'National ID'],
                'sample' => ['John Doe', 'john@example.com', '+254712345678', '12345678'],
            ],
            'leases' => [
                'headers' => ['Unit Number', 'Tenant Email', 'Rent Amount', 'Deposit Amount', 'Start Date', 'End Date'],
                'sample' => ['A101', 'john@example.com', '15000', '30000', '2024-01-01', '2024-12-31'],
            ],
            'water_readings' => [
                'headers' => ['Unit Number', 'Reading Date', 'Previous Reading', 'Current Reading', 'Status'],
                'sample' => ['A101', '2024-01-15', '100', '125', 'approved'],
            ],
            'invoices' => [
                'headers' => ['Unit Number', 'Invoice Number', 'Invoice Date', 'Due Date', 'Rent Charge', 'Water Charge', 'Previous Arrears', 'Status', 'Paid Amount'],
                'sample' => ['A101', 'INV-202401-0001', '2024-01-01', '2024-01-05', '15000', '750', '0', 'paid', '15750'],
            ],
            'payments' => [
                'headers' => ['Invoice Number', 'Payment Date', 'Amount', 'Payment Method', 'Reference Number'],
                'sample' => ['INV-202401-0001', '2024-01-03', '15750', 'mpesa', 'RBK12345678'],
            ],
            'units' => [
                'headers' => ['Building Name', 'Unit Number', 'Floor Number', 'Type', 'Target Rent', 'Status'],
                'sample' => ['Block A', 'A101', '1', '2br', '15000', 'vacant'],
            ],
            default => [],
        };
    }
}
