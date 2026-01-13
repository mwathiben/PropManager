<?php

namespace App\Imports;

use App\Models\BankReconciliationQueue;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;

class BankStatementImport
{
    protected int $landlordId;

    protected string $bankCode;

    protected array $columnMapping;

    protected int $importedCount = 0;

    protected int $skippedCount = 0;

    protected array $errors = [];

    public function __construct(int $landlordId, string $bankCode, array $columnMapping = [])
    {
        $this->landlordId = $landlordId;
        $this->bankCode = $bankCode;
        $this->columnMapping = $columnMapping;
    }

    public function import(UploadedFile $file): self
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if (in_array($extension, ['csv', 'txt'])) {
            $this->importCsv($file);
        } else {
            $this->importExcel($file);
        }

        return $this;
    }

    private function importCsv(UploadedFile $file): void
    {
        $handle = fopen($file->getRealPath(), 'r');

        if ($handle === false) {
            $this->errors[] = 'Could not open file';

            return;
        }

        $headers = fgetcsv($handle);

        if ($headers === false) {
            fclose($handle);
            $this->errors[] = 'Could not read headers';

            return;
        }

        $headers = array_map(fn ($h) => strtolower(trim(str_replace([' ', '-'], '_', $h))), $headers);

        $rowNumber = 2;

        while (($data = fgetcsv($handle)) !== false) {
            if (count($data) !== count($headers)) {
                $this->skippedCount++;
                $rowNumber++;

                continue;
            }

            $row = array_combine($headers, $data);
            $this->processRow($row, $rowNumber);
            $rowNumber++;
        }

        fclose($handle);
    }

    private function importExcel(UploadedFile $file): void
    {
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file->getRealPath());
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray();

        if (empty($rows)) {
            $this->errors[] = 'Empty file';

            return;
        }

        $headers = array_map(fn ($h) => strtolower(trim(str_replace([' ', '-'], '_', $h ?? ''))), $rows[0]);

        for ($i = 1; $i < count($rows); $i++) {
            if (count($rows[$i]) !== count($headers)) {
                $this->skippedCount++;

                continue;
            }

            $row = array_combine($headers, $rows[$i]);
            $this->processRow($row, $i + 1);
        }
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            $rowArray = $row instanceof Collection ? $row->toArray() : (array) $row;
            $this->processRow($rowArray, $index + 2);
        }
    }

    private function processRow(array $row, int $rowNumber): void
    {
        try {
            $reference = $this->getColumnValue($row, 'reference');
            $amount = $this->parseAmount($this->getColumnValue($row, 'amount'));
            $date = $this->parseDate($this->getColumnValue($row, 'date'));
            $description = $this->getColumnValue($row, 'description') ?? $reference;

            if (! $reference || $amount <= 0) {
                $this->skippedCount++;

                return;
            }

            $exists = BankReconciliationQueue::where('landlord_id', $this->landlordId)
                ->where('transaction_reference', $reference)
                ->where('bank_code', $this->bankCode)
                ->exists();

            if ($exists) {
                $this->skippedCount++;

                return;
            }

            BankReconciliationQueue::create([
                'landlord_id' => $this->landlordId,
                'bank_code' => $this->bankCode,
                'transaction_reference' => $reference,
                'amount' => $amount,
                'status' => 'pending',
                'raw_payload' => [
                    'reference' => $reference,
                    'description' => $description,
                    'amount' => $amount,
                    'date' => $date?->format('Y-m-d'),
                    'source' => 'csv_import',
                    'row_number' => $rowNumber,
                ],
            ]);

            $this->importedCount++;
        } catch (\Exception $e) {
            $this->errors[] = 'Row '.$rowNumber.': '.$e->getMessage();
            $this->skippedCount++;
        }
    }

    private function getColumnValue(array $row, string $field): ?string
    {
        $column = $this->columnMapping[$field] ?? $this->getDefaultColumn($field);
        $column = strtolower(str_replace([' ', '-'], '_', $column));

        foreach ($row as $key => $value) {
            $normalizedKey = strtolower(str_replace([' ', '-'], '_', $key));

            if ($normalizedKey === $column) {
                return trim((string) $value);
            }
        }

        return null;
    }

    private function getDefaultColumn(string $field): string
    {
        return match ($field) {
            'reference' => 'reference',
            'amount' => 'amount',
            'date' => 'date',
            'description' => 'description',
            default => $field,
        };
    }

    private function parseAmount(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0;
        }

        $cleaned = preg_replace('/[^0-9.\-]/', '', (string) $value);

        return abs((float) $cleaned);
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Exception $e) {
            $formats = ['d/m/Y', 'd-m-Y', 'Y/m/d', 'm/d/Y', 'd M Y', 'd-M-Y'];

            foreach ($formats as $format) {
                try {
                    return Carbon::createFromFormat($format, $value);
                } catch (\Exception $e) {
                    continue;
                }
            }

            return null;
        }
    }

    public function getImportedCount(): int
    {
        return $this->importedCount;
    }

    public function getSkippedCount(): int
    {
        return $this->skippedCount;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getResults(): array
    {
        return [
            'imported' => $this->importedCount,
            'skipped' => $this->skippedCount,
            'errors' => $this->errors,
        ];
    }
}
