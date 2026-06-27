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

        if ($this->failsSizeCheck($value, $fail)) {
            return;
        }

        $extension = strtolower($value->getClientOriginalExtension());

        if ($this->failsExtensionChecks($extension, $fail)) {
            return;
        }

        $mimeType = $value->getMimeType();

        if ($this->failsMimeCheck($mimeType, $fail)) {
            return;
        }

        if ($this->failsContentChecks($value, $mimeType, $fail)) {
            return;
        }
    }

    /**
     * Return true (and call $fail) when the file exceeds the maximum size.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    private function failsSizeCheck(UploadedFile $value, Closure $fail): bool
    {
        $maxBytes = $this->maxSize * 1024 * 1024;

        if ($value->getSize() > $maxBytes) {
            $fail("The :attribute must not be larger than {$this->maxSize}MB.");

            return true;
        }

        return false;
    }

    /**
     * Return true (and call $fail) when the extension is blocked or not allowed.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    private function failsExtensionChecks(string $extension, Closure $fail): bool
    {
        if (in_array($extension, $this->dangerousExtensions)) {
            $fail('The :attribute has a file type that is not allowed for security reasons.');

            return true;
        }

        if (! empty($this->allowedExtensions) && ! in_array($extension, $this->allowedExtensions)) {
            $allowed = implode(', ', $this->allowedExtensions);
            $fail("The :attribute must be a file of type: {$allowed}.");

            return true;
        }

        return false;
    }

    /**
     * Return true (and call $fail) when the MIME type is not in the allowed list.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    private function failsMimeCheck(?string $mimeType, Closure $fail): bool
    {
        if (! empty($this->allowedMimes) && ! in_array($mimeType, $this->allowedMimes)) {
            $fail('The :attribute has an invalid file type.');

            return true;
        }

        return false;
    }

    /**
     * Return true (and call $fail) when the file content fails signature or PHP-code checks.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    private function failsContentChecks(UploadedFile $value, ?string $mimeType, Closure $fail): bool
    {
        if (! $this->verifyFileSignature($value, $mimeType ?? '')) {
            $fail('The :attribute appears to be corrupted or has been tampered with.');

            return true;
        }

        if ($this->containsPhpCode($value)) {
            $fail('The :attribute contains invalid content.');

            return true;
        }

        return false;
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

        $content = $file->get();
        if ($content === false) {
            return false;
        }

        // Get first 20 bytes for signature checking
        $header = substr($content, 0, 20);

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
        $content = $file->get();

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
