<?php

namespace App\Jobs;

use App\Mail\DataExportReady;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\DataExportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
        $recentExportLog = AuditLog::where('user_id', $this->user->id)
            ->where('event_type', 'data_exported')
            ->where('created_at', '>=', now()->subHour())
            ->first();

        if ($recentExportLog) {
            Log::info('ExportUserData: Recent export exists, skipping', [
                'user_id' => $this->user->id,
                'existing_log_id' => $recentExportLog->id,
                'existing_export_path' => $recentExportLog->metadata['export_path'] ?? null,
            ]);

            return;
        }

        // Phase-59 PATH-CAVEAT-2: DataExportService::exportUserData now
        // returns the tenant-disk-relative path (was absolute).
        $relativePath = $exportService->exportUserData($this->user);

        DB::transaction(function () use ($relativePath) {
            $existingLog = AuditLog::where('user_id', $this->user->id)
                ->where('event_type', 'data_exported')
                ->where('created_at', '>=', now()->subHour())
                ->lockForUpdate()
                ->first();

            if ($existingLog) {
                Log::info('ExportUserData: Export created by concurrent job, skipping audit log', [
                    'user_id' => $this->user->id,
                ]);

                return;
            }

            AuditLog::create([
                'user_id' => $this->user->id,
                'landlord_id' => $this->user->isLandlord() ? $this->user->id : $this->user->landlord_id,
                'event_type' => 'data_exported',
                'auditable_type' => User::class,
                'auditable_id' => $this->user->id,
                'metadata' => [
                    'export_path' => $relativePath,
                    'compliance' => ['gdpr_article_20', 'kenya_dpa_section_26'],
                    'job_id' => $this->job?->uuid(),
                ],
                'ip_address' => null,
                'user_agent' => 'Background Job',
            ]);
        });

        if ($this->sendEmail) {
            Mail::to($this->user->email)->queue(new DataExportReady($this->user, $relativePath));
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ExportUserData: Data export failed', [
            'user_id' => $this->user->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
