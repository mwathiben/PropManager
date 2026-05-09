<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class OcrService
{
    /**
     * Process an image and extract meter reading using configured OCR provider
     */
    public function extractMeterReading($image, ?int $landlordId = null): ?array
    {
        $provider = Setting::get('ocr_provider', 'none', $landlordId);

        if ($provider === 'none' || $provider === null) {
            return null; // OCR not configured
        }

        try {
            return match ($provider) {
                'ocr_space' => $this->processWithOcrSpace($image, $landlordId),
                'google_vision' => $this->processWithGoogleVision($image, $landlordId),
                'azure_vision' => $this->processWithAzureVision($image, $landlordId),
                'tesseract' => $this->processWithTesseract($image, $landlordId),
                default => null,
            };
        } catch (\Exception $e) {
            Log::error('OCR processing failed', [
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * OCR.space provider (Recommended for MVP - 25,000 free requests/month)
     */
    protected function processWithOcrSpace($image, ?int $landlordId): ?array
    {
        $apiKey = Setting::get('ocr_space_api_key', null, $landlordId);

        if (! $apiKey) {
            return null;
        }

        // Convert image to base64
        $base64Image = base64_encode($this->getImageContent($image));

        // HANDLE-7: bound the OCR call so a hung provider can't tie up the
        // request thread (this code can run on the request path).
        $response = Http::asForm()
            ->connectTimeout(3)
            ->timeout(8)
            ->post('https://api.ocr.space/parse/image', [
                'apikey' => $apiKey,
                'base64Image' => 'data:image/jpeg;base64,'.$base64Image,
                'language' => 'eng',
                'isOverlayRequired' => false,
                'detectOrientation' => true,
                'scale' => true,
                'OCREngine' => 2, // Engine 2 is better for digits
            ]);

        if ($response->successful()) {
            $data = $response->json();

            if (isset($data['ParsedResults'][0]['ParsedText'])) {
                $text = $data['ParsedResults'][0]['ParsedText'];
                $reading = $this->extractNumericReading($text);

                return [
                    'success' => true,
                    'reading' => $reading,
                    'raw_text' => $text,
                    'confidence' => $data['ParsedResults'][0]['TextOrientation'] ?? null,
                    'provider' => 'ocr_space',
                ];
            }
        }

        return null;
    }

    /**
     * Google Cloud Vision API
     */
    protected function processWithGoogleVision($image, ?int $landlordId): ?array
    {
        $apiKey = Setting::get('google_vision_api_key', null, $landlordId);

        if (! $apiKey) {
            return null;
        }

        // Convert image to base64
        $base64Image = base64_encode($this->getImageContent($image));

        // HANDLE-7: bound the OCR call so a hung provider can't tie up the
        // request thread.
        $response = Http::withHeaders([
            'x-goog-api-key' => $apiKey,
            'Content-Type' => 'application/json',
        ])->connectTimeout(3)->timeout(8)->post('https://vision.googleapis.com/v1/images:annotate', [
            'requests' => [
                [
                    'image' => [
                        'content' => $base64Image,
                    ],
                    'features' => [
                        [
                            'type' => 'TEXT_DETECTION',
                            'maxResults' => 1,
                        ],
                    ],
                ],
            ],
        ]);

        if ($response->successful()) {
            $data = $response->json();

            if (isset($data['responses'][0]['textAnnotations'][0]['description'])) {
                $text = $data['responses'][0]['textAnnotations'][0]['description'];
                $reading = $this->extractNumericReading($text);

                return [
                    'success' => true,
                    'reading' => $reading,
                    'raw_text' => $text,
                    'confidence' => null,
                    'provider' => 'google_vision',
                ];
            }
        }

        return null;
    }

    /**
     * Azure Computer Vision API
     */
    protected function processWithAzureVision($image, ?int $landlordId): ?array
    {
        $apiKey = Setting::get('azure_vision_api_key', null, $landlordId);
        $endpoint = Setting::get('azure_vision_endpoint', null, $landlordId);

        if (! $apiKey || ! $endpoint) {
            return null;
        }

        // Read image content
        $imageContent = $this->getImageContent($image);

        // HANDLE-7: bound the OCR call so a hung provider can't tie up the
        // request thread.
        $response = Http::withHeaders([
            'Ocp-Apim-Subscription-Key' => $apiKey,
            'Content-Type' => 'application/octet-stream',
        ])->connectTimeout(3)->timeout(8)
            ->withBody($imageContent, 'application/octet-stream')
            ->post("{$endpoint}/vision/v3.2/read/analyze");

        if ($response->successful()) {
            $operationLocation = $response->header('Operation-Location');

            // Poll for results
            sleep(2); // Wait for processing

            $resultResponse = Http::withHeaders([
                'Ocp-Apim-Subscription-Key' => $apiKey,
            ])->connectTimeout(3)->timeout(8)->get($operationLocation);

            if ($resultResponse->successful()) {
                $data = $resultResponse->json();

                if (isset($data['analyzeResult']['readResults'][0]['lines'])) {
                    $text = collect($data['analyzeResult']['readResults'][0]['lines'])
                        ->pluck('text')
                        ->implode(' ');

                    $reading = $this->extractNumericReading($text);

                    return [
                        'success' => true,
                        'reading' => $reading,
                        'raw_text' => $text,
                        'confidence' => null,
                        'provider' => 'azure_vision',
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Tesseract OCR (Self-hosted)
     * Note: Requires tesseract-ocr to be installed on the server
     */
    protected function processWithTesseract($image, ?int $landlordId): ?array
    {
        // Check if tesseract is installed
        if (! function_exists('shell_exec')) {
            return null;
        }

        // Tesseract requires a filesystem path
        $imagePath = $image instanceof UploadedFile
            ? $image->getRealPath()
            : Storage::disk('local')->path($image);

        // Run tesseract
        $command = 'tesseract '.escapeshellarg($imagePath).' stdout --psm 6 digits';
        $output = shell_exec($command);

        if ($output) {
            $reading = $this->extractNumericReading($output);

            return [
                'success' => true,
                'reading' => $reading,
                'raw_text' => trim($output),
                'confidence' => null,
                'provider' => 'tesseract',
            ];
        }

        return null;
    }

    /**
     * Extract numeric reading from OCR text
     * Looks for patterns like: 12345, 12345.67, 1,234.56
     */
    protected function extractNumericReading(string $text): ?float
    {
        // Remove whitespace and newlines
        $text = preg_replace('/\s+/', '', $text);

        // Pattern 1: Look for decimal numbers (handles both . and , as decimal separator)
        if (preg_match('/(\d+[.,]\d+)/', $text, $matches)) {
            $number = str_replace(',', '.', $matches[1]);

            return (float) $number;
        }

        // Pattern 2: Look for integers
        if (preg_match('/(\d+)/', $text, $matches)) {
            return (float) $matches[1];
        }

        return null;
    }

    /**
     * Test OCR configuration
     */
    public function testConnection(?int $landlordId = null): array
    {
        $provider = Setting::get('ocr_provider', 'none', $landlordId);

        if ($provider === 'none' || $provider === null) {
            return [
                'success' => false,
                'message' => 'No OCR provider configured',
            ];
        }

        // Create a simple test image with text "12345"
        $testImage = $this->createTestImage();

        $result = $this->extractMeterReading($testImage, $landlordId);

        if ($result && $result['success']) {
            return [
                'success' => true,
                'message' => "OCR provider '{$provider}' is working correctly",
                'test_result' => $result,
            ];
        }

        return [
            'success' => false,
            'message' => "OCR provider '{$provider}' failed to process test image",
        ];
    }

    /**
     * Create a simple test image (placeholder - would need GD or Imagick)
     */
    protected function createTestImage()
    {
        // For now, return null - in production, this would create a test image
        // with the text "12345" using GD or Imagick
        return null;
    }

    /**
     * Get available OCR providers
     */
    public static function getAvailableProviders(): array
    {
        return [
            'ocr_space' => [
                'name' => 'OCR.space',
                'description' => 'Free OCR API with 25,000 requests/month',
                'free_tier' => '25,000 requests/month',
                'setup_url' => 'https://ocr.space/ocrapi',
                'requires' => ['API Key'],
                'recommended' => true,
            ],
            'google_vision' => [
                'name' => 'Google Cloud Vision',
                'description' => 'Highly accurate OCR from Google',
                'free_tier' => '1,000 requests/month',
                'setup_url' => 'https://cloud.google.com/vision',
                'requires' => ['API Key'],
                'recommended' => false,
            ],
            'azure_vision' => [
                'name' => 'Azure Computer Vision',
                'description' => 'Microsoft Azure OCR service',
                'free_tier' => '5,000 transactions/month',
                'setup_url' => 'https://azure.microsoft.com/en-us/services/cognitive-services/computer-vision/',
                'requires' => ['API Key', 'Endpoint URL'],
                'recommended' => false,
            ],
            'tesseract' => [
                'name' => 'Tesseract OCR (Self-Hosted)',
                'description' => 'Open-source OCR engine - requires server installation',
                'free_tier' => 'Unlimited (self-hosted)',
                'setup_url' => 'https://github.com/tesseract-ocr/tesseract',
                'requires' => ['Server Installation'],
                'recommended' => false,
            ],
        ];
    }

    /**
     * Get image content from UploadedFile or storage path.
     *
     * @param  UploadedFile|string  $image  UploadedFile instance or storage path
     */
    protected function getImageContent(UploadedFile|string $image): string
    {
        if ($image instanceof UploadedFile) {
            return $image->get();
        }

        return Storage::disk('local')->get($image);
    }
}
