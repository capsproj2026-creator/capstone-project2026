<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\ViolationLog;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ViolationEvidenceViewTest extends TestCase
{
    private ?ViolationLog $log = null;

    protected function tearDown(): void
    {
        try {
            $this->log?->delete();
        } catch (\Throwable) {
        }

        parent::tearDown();
    }

    private function staffUser(string $role): User
    {
        try {
            $user = User::query()
                ->whereHas('role', fn ($q) => $q->where('role_name', $role))
                ->first();
        } catch (\Throwable $e) {
            $this->markTestSkipped('MongoDB unavailable: '.$e->getMessage());
        }

        if (! $user) {
            $this->markTestSkipped("No {$role} user found.");
        }

        return $user;
    }

    private function logWithEvidence(): ViolationLog
    {
        Storage::fake('private');
        $path = 'violation-evidence/test-'.uniqid().'.png';
        Storage::disk('private')->put($path, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        ));

        return $this->log = ViolationLog::query()->create([
            'violator_name' => 'Evidence View Test',
            'user_type' => 'Unregistered',
            'plate_number' => 'EVT'.random_int(100, 999),
            'violation_type' => 'Wrong Parking',
            'violation_types' => ['Wrong Parking'],
            'description' => 'Evidence view test',
            'evidence_photo' => $path,
            'evidence_photos' => [$path],
            'status' => 'Active',
            'created_at' => now(),
        ]);
    }

    public function test_guard_can_open_uploaded_evidence_photo(): void
    {
        $guard = $this->staffUser('Guard');
        $log = $this->logWithEvidence();

        $this->actingAs($guard)
            ->get(route('guard.violations.evidence', ['id' => (string) $log->getKey(), 'index' => 0]))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');

        $this->actingAs($guard)
            ->get(route('guard.violations'))
            ->assertOk()
            ->assertSee(route('guard.violations.evidence', ['id' => (string) $log->getKey(), 'index' => 0]), false);
    }

    public function test_admin_can_open_uploaded_evidence_photo(): void
    {
        $admin = $this->staffUser('Admin');
        $log = $this->logWithEvidence();

        $this->actingAs($admin)
            ->get(route('admin.violations.evidence', ['id' => (string) $log->getKey(), 'index' => 0]))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');

        $this->actingAs($admin)
            ->get(route('admin.violations'))
            ->assertOk()
            ->assertSee(route('admin.violations.evidence', ['id' => (string) $log->getKey(), 'index' => 0]), false);
    }

    public function test_missing_evidence_file_returns_not_found_instead_of_error(): void
    {
        $guard = $this->staffUser('Guard');
        $log = $this->logWithEvidence();
        Storage::disk('private')->delete($log->evidence_photo);

        $this->actingAs($guard)
            ->get(route('guard.violations.evidence', ['id' => (string) $log->getKey(), 'index' => 0]))
            ->assertNotFound();
    }
}
