<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UploadLogoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->isLandlord();
    }

    public function rules(): array
    {
        // VALID-1: SVG removed — SVGs are XML, not raster, and execute embedded
        // <script>/onload= when rendered inline in invoice PDFs and tenant-facing
        // dashboards. The logo renders for every tenant of the uploading
        // landlord, so a malicious or compromised landlord could plant stored
        // XSS that fires across their entire fleet. GIF removed too: animated
        // GIFs and polyglot files (GIFAR) are needless attack surface for what
        // is meant to be a static brand mark. mimetypes:image/jpeg,image/png
        // performs server-side MIME sniffing — stronger than the extension-based
        // mimes: rule, which can be spoofed.
        return [
            'logo' => [
                'required',
                'image',
                'mimes:jpeg,png,jpg',
                'mimetypes:image/jpeg,image/png',
                'max:2048',
                'dimensions:max_width=2000,max_height=2000',
            ],
        ];
    }
}
