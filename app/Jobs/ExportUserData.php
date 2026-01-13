<?php

namespace App\Jobs;

use App\Mail\DataExportReady;
use App\Models\User;
use App\Services\DataExportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class ExportUserData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 600; // 10 minutes

    /**
     * Create a new job instance.
     */
    public function __construct(
        public User $user,
        public bool $sendEmail = true
    ) {}

    /**
     * Execute the job.
     */
    public function handle(DataExportService $exportService): void
    {
        $zipPath = $exportService->exportUserData($this->user);

        // Store the export path in a way that can be accessed later
        $relativePath = str_replace(storage_path('app/'), '', $zipPath);

        // Log the export
        \App\Models\AuditLog::create([
            'user_id' => $this->user->id,
            'landlord_id' => $this->user->isLandlord() ? $this->user->id : $this->user->landlord_id,
            'event_type' => 'data_exported',
            'auditable_type' => User::class,
            'auditable_id' => $this->user->id,
            'metadata' => [
                'export_path' => $relativePath,
                'compliance' => ['gdpr_article_20', 'kenya_dpa_section_26'],
            ],
            'ip_address' => null, // Background job
            'user_agent' => 'Background Job',
        ]);

        // Send email notification if requested
        if ($this->sendEmail) {
            Mail::to($this->user->email)->queue(new DataExportReady($this->user, $relativePath));
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        \Log::error('Data export failed for user '.$this->user->id, [
            'error' => $exception->getMessage(),
        ]);
    }
}
