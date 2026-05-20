<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\VendorPortalLinkMailable;
use App\Models\Vendor;
use App\Services\Vendors\VendorPortalLinkService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Phase-70 VENDOR-AUTH-3: operator re-issue of a vendor portal link.
 * Prints the signed URL and (when the vendor has an email + --send)
 * queues it. Bypasses TenantScope to find the vendor by id.
 */
class VendorPortalLink extends Command
{
    protected $signature = 'vendor:portal-link {vendor : Vendor id} {--send : Also email the link to the vendor}';

    protected $description = 'Mint (and optionally email) a signed vendor-portal magic-link';

    public function handle(VendorPortalLinkService $links): int
    {
        $vendor = Vendor::withoutGlobalScopes()->find((int) $this->argument('vendor'));

        if ($vendor === null) {
            $this->error('Vendor not found.');

            return self::FAILURE;
        }

        $url = $links->issue($vendor);
        $this->info("Portal link for vendor #{$vendor->id} ({$vendor->name}):");
        $this->line($url);

        if ($this->option('send')) {
            if (! $vendor->email) {
                $this->warn('Vendor has no email — link printed only.');

                return self::SUCCESS;
            }

            Mail::to($vendor->email)->queue(new VendorPortalLinkMailable($vendor, $url));
            $this->info("Queued to {$vendor->email}.");
        }

        return self::SUCCESS;
    }
}
