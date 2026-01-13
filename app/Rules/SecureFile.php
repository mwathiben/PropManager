<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

class SecureFile implements ValidationRule
{
    /**
     * File signature (magic bytes) mappings.
     */
    protected array $signatures = [
        'image/jpeg' => ["\xFF\xD8\xFF"],
        'image/png' => ["\x89\x50\x4E\x47\x0D\x0A\x1A\x0A"],
        'image/gif' => ['GIF87a', 'GIF89a'],
        'image/webp' => ['RIFF'],
        'application/pdf' => ['%PDF'],
        'application/msword' => ["\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1"], // DOC
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ["PK\x03\x04"], // DOCX
        'application/vnd.ms-excel' => ["\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1"], // XLS
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => ["PK\x03\x04"], // XLSX
        'application/zip' => ["PK\x03\x04"],
    ];

    /**
     * Dangerous file extensions that should always be blocked.
     */
    protected array $dangerousExtensions = [
        'php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'phps', 'phar',
        'exe', 'bat', 'cmd', 'com', 'msi', 'scr',
        'js', 'jse', 'vbs', 'vbe', 'wsf', 'wsh', 'ps1', 'psm1',
        'sh', 'bash', 'csh', 'ksh',
        'jar', 'class',
        'htaccess', 'htpasswd',
        'asp', 'aspx', 'cer',
        'cgi', 'pl', 'py', 'rb',
        'svg', // Can contain scripts
    ];

    protected ?int $maxSize = null;

    protected array $allowedMimes = [];

    protected array $allowedExtensions = [];

    public function __construct(?int $maxSizeMb = null, array $allowedMimes = [], array $allowedExtensions = [])
    {
        $this->maxSize = $maxSizeMb ?? config('security.uploads.max_size_mb', 10);
        $this->allowedMimes = $allowedMimes ?: config('security.uploads.allowed_mimes', []);
        $this->allowedExtensions = $allowedExtensions ?: config('security.uploads.allowed_extensions', []);
    }

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            $fail('The :attribute must be a valid file.');

            return;
        }

        // Check file size
        $maxBytes = $this->maxSize * 1024 * 1024;
        if ($value->getSize() > $maxBytes) {
            $fail("The :attribute must not be larger than {$this->maxSize}MB.");

            return;
        }

        // Get file extension
        $extension = strtolower($value->getClientOriginalExtension());

        // Block dangerous extensions
        if (in_array($extension, $this->dangerousExtensions)) {
            $fail('The :attribute has a file type that is not allowed for security reasons.');

            return;
        }

        // Check allowed extensions if specified
        if (! empty($this->allowedExtensions) && ! in_array($extension, $this->allowedExtensions)) {
            $allowed = implode(', ', $this->allowedExtensions);
            $fail("The :attribute must be a file of type: {$allowed}.");

            return;
        }

        // Get MIME type
        $mimeType = $value->getMimeType();

        // Check allowed MIME types if specified
        if (! empty($this->allowedMimes) && ! in_array($mimeType, $this->allowedMimes)) {
            $fail('The :attribute has an invalid file type.');

            return;
        }

        // Verify file signature (magic bytes) matches claimed MIME type
        if (! $this->verifyFileSignature($value, $mimeType)) {
            $fail('The :attribute appears to be corrupted or has been tampered with.');

            return;
        }

        // Check for PHP code in file content (additional safety)
        if ($this->containsPhpCode($value)) {
            $fail('The :attribute contains invalid content.');

            return;
        }
    }

    /**
     * Verify file signature matches the MIME type.
     */
    protected function verifyFileSignature(UploadedFile $file, string $mimeType): bool
    {
        // If we don't have a signature for this MIME type, allow it (but it passed other checks)
        if (! isset($this->signatures[$mimeType])) {
            return true;
        }

        $handle = fopen($file->getRealPath(), 'rb');
        if (! $handle) {
            return false;
        }

        // Read first 20 bytes for signature checking
        $header = fread($handle, 20);
        fclose($handle);

        foreach ($this->signatures[$mimeType] as $signature) {
            if (str_starts_with($header, $signature)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if file contains PHP code (for image uploads that might be PHP files).
     */
    protected function containsPhpCode(UploadedFile $file): bool
    {
        $content = file_get_contents($file->getRealPath());

        // Check for PHP tags
        $patterns = [
            '/<\?php/i',
            '/<\?=/i',
            '/<\?[\s\n]/i',
            '/<script[^>]*language\s*=\s*["\']?php["\']?/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Create a rule for images only.
     */
    public static function image(?int $maxSizeMb = 5): static
    {
        return new static(
            $maxSizeMb,
            ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
            ['jpg', 'jpeg', 'png', 'gif', 'webp']
        );
    }

    /**
     * Create a rule for documents only.
     */
    public static function document(?int $maxSizeMb = 10): static
    {
        return new static(
            $maxSizeMb,
            [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ],
            ['pdf', 'doc', 'docx']
        );
    }
}
