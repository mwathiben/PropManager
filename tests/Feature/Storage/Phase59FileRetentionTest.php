<?php

declare(strict_types=1);

namespace Tests\Feature\Storage;

use App\Models\FileRetentionPolicy;
use App\Services\Storage\FileRetentionService;
use Database\Seeders\Phase59FileRetentionPolicySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Phase-59 FILE-RETENTION-1/2/3: file_retention_policies + service +
 * cron.
 */
class Phase59FileRetentionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->seed(Phase59FileRetentionPolicySeeder::class);
    }

    public function test_seeder_creates_seven_platform_defaults(): void
    {
        $count = FileRetentionPolicy::whereNull('landlord_id')->count();
        $this->assertSame(7, $count);
    }

    public function test_resolve_for_returns_platform_default_for_unknown_landlord(): void
    {
        $days = FileRetentionPolicy::resolveFor('ocr_temp', 42);

        $this->assertSame(1, $days);
    }

    public function test_resolve_for_prefers_landlord_override(): void
    {
        $landlord = \App\Models\User::factory()->create();
        FileRetentionPolicy::create([
            'subject' => 'ocr_temp',
            'retention_days' => 7,
            'landlord_id' => $landlord->id,
        ]);
        Cache::flush();

        $days = FileRetentionPolicy::resolveFor('ocr_temp', $landlord->id);

        $this->assertSame(7, $days);
    }

    public function test_subjects_constant_lists_seven_subjects(): void
    {
        $this->assertCount(7, FileRetentionPolicy::SUBJECTS);
        $this->assertContains('lease_doc', FileRetentionPolicy::SUBJECTS);
        $this->assertContains('kyc_doc', FileRetentionPolicy::SUBJECTS);
        $this->assertContains('file_access_audit', FileRetentionPolicy::SUBJECTS);
    }

    public function test_service_purges_expired_files_from_directory_subject(): void
    {
        Storage::fake('local');

        // OCR temp retention is 1 day. Create files dated 5 days ago.
        $oldPath = 'ocr-temp/old.png';
        $newPath = 'ocr-temp/recent.png';
        Storage::tenant()->put($oldPath, 'old');
        Storage::tenant()->put($newPath, 'recent');
        touch(Storage::tenant()->path($oldPath), now()->subDays(5)->getTimestamp());

        $service = app(FileRetentionService::class);
        $result = $service->enforce('ocr_temp');

        $this->assertSame(1, $result['deleted']);
        $this->assertFalse(Storage::tenant()->exists($oldPath));
        $this->assertTrue(Storage::tenant()->exists($newPath));
    }

    public function test_service_dry_run_does_not_delete(): void
    {
        Storage::fake('local');

        $oldPath = 'ocr-temp/old.png';
        Storage::tenant()->put($oldPath, 'old');
        touch(Storage::tenant()->path($oldPath), now()->subDays(5)->getTimestamp());

        $service = app(FileRetentionService::class);
        $result = $service->enforce('ocr_temp', dryRun: true);

        $this->assertSame(1, $result['deleted']);
        $this->assertTrue(Storage::tenant()->exists($oldPath));
    }

    public function test_service_returns_zero_when_subject_has_no_files(): void
    {
        Storage::fake('local');

        $service = app(FileRetentionService::class);
        $result = $service->enforce('ocr_temp');

        $this->assertSame(['deleted' => 0, 'errors' => 0], $result);
    }

    public function test_storage_enforce_retention_command_scheduled_at_0230(): void
    {
        $events = collect(Schedule::events());
        $entry = $events->first(fn ($e) => str_contains((string) $e->command, 'storage:enforce-retention'));

        $this->assertNotNull($entry);
        $this->assertSame('30 2 * * *', $entry->expression);
    }

    public function test_storage_enforce_retention_command_class_exists(): void
    {
        $this->assertTrue(class_exists(\App\Console\Commands\StorageEnforceRetention::class));
        $command = new \App\Console\Commands\StorageEnforceRetention;
        $this->assertSame('storage:enforce-retention', $command->getName());
    }

    public function test_dry_run_flag_is_advertised(): void
    {
        $command = new \App\Console\Commands\StorageEnforceRetention;
        $this->assertStringContainsString('--dry-run', $command->getSynopsis());
    }
}
