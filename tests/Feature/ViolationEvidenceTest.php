<?php

namespace Tests\Feature;

use App\Mail\VehicleViolationMail;
use App\Models\User;
use App\Models\ViolationLog;
use App\Models\ViolationType;
use App\Support\ViolationEvidence;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ViolationEvidenceTest extends TestCase
{
    private function seedUserOrSkip(string $email): User
    {
        try {
            $user = User::query()->where('email', $email)->first();
        } catch (\Throwable $e) {
            $this->markTestSkipped('MongoDB unavailable: '.$e->getMessage());
        }

        if (! $user) {
            $this->markTestSkipped("Seeded user missing: {$email}");
        }

        return $user;
    }

    private function fakeJpegUpload(string $name = 'evidence.jpg'): UploadedFile
    {
        // Minimal valid JPEG (1x1) — avoids requiring PHP GD for UploadedFile::fake()->image().
        $binary = base64_decode(
            '/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgNDRgyIRwhMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjL/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAn/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAGfAP/EABQQAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQEAAQUCf//EABQRAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQMBAT8Bf//EABQRAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQIBAT8Bf//Z'
        );

        $tmp = tempnam(sys_get_temp_dir(), 'vev');
        file_put_contents($tmp, $binary);

        return new UploadedFile($tmp, $name, 'image/jpeg', null, true);
    }

    public function test_evidence_paths_support_single_and_multiple_photos(): void
    {
        $single = new ViolationLog(['evidence_photo' => 'violation-evidence/a.jpg']);
        $this->assertSame(['violation-evidence/a.jpg'], ViolationEvidence::pathsFor($single));

        $multiple = new ViolationLog([
            'evidence_photo' => 'violation-evidence/a.jpg',
            'evidence_photos' => ['violation-evidence/a.jpg', 'violation-evidence/b.jpg'],
        ]);
        $this->assertSame(
            ['violation-evidence/a.jpg', 'violation-evidence/b.jpg'],
            ViolationEvidence::pathsFor($multiple)
        );

        $empty = new ViolationLog([]);
        $this->assertSame([], ViolationEvidence::pathsFor($empty));
        $this->assertFalse(ViolationEvidence::hasEvidence($empty));
    }

    public function test_guard_can_upload_violation_with_photo_evidence(): void
    {
        $guard = $this->seedUserOrSkip('guard@my.cspc.edu.ph');
        if (! $guard->hasVerifiedEmail()) {
            $guard->update(['email_verified_at' => now()]);
        }

        try {
            $owner = User::query()
                ->whereNotNull('plate_number')
                ->whereNotIn('plate_number', ['', 'N/A', 'n/a'])
                ->whereIn('user_role_id', [3, 4])
                ->first();
        } catch (\Throwable $e) {
            $this->markTestSkipped('MongoDB unavailable: '.$e->getMessage());
        }

        if (! $owner) {
            $this->markTestSkipped('No student/staff with plate found.');
        }

        $type = ViolationType::query()->where('status', 'Active')->value('violation_name');
        if (! $type) {
            $this->markTestSkipped('No active violation type found.');
        }

        Mail::fake();

        $response = $this->actingAs($guard)
            ->post(route('guard.violations.store'), [
                'plate_number' => $owner->plate_number,
                'violation_type' => $type,
                'description' => 'Test upload with evidence',
                'evidence_photos' => [$this->fakeJpegUpload()],
            ]);

        $response->assertRedirect();
        $this->assertStringContainsString('success=1', (string) $response->headers->get('Location'));

        $log = ViolationLog::query()
            ->where('user_id', $owner->id)
            ->where('description', 'Test upload with evidence')
            ->orderByDesc('created_at')
            ->first();

        $this->assertNotNull($log);
        $this->assertTrue($log->hasEvidence());
        $this->assertNotEmpty($log->evidence_photo);
        $this->assertTrue(Storage::disk('public')->exists($log->evidence_photo));
        $this->assertCount(1, $log->evidencePaths());

        $urls = ViolationEvidence::urlsFor($log, 'guard.violations.evidence');
        $this->assertNotEmpty($urls);
        $this->assertStringContainsString('/storage/violation-evidence/', $urls[0]);

        Mail::assertSent(VehicleViolationMail::class);

        Storage::disk('public')->delete($log->evidence_photo);
        ViolationLog::query()->where('_id', $log->getKey())->delete();
    }

    public function test_user_can_view_own_violation_evidence_only(): void
    {
        $storedPath = 'violation-evidence/auth-test-'.uniqid().'.jpg';
        Storage::disk('public')->put($storedPath, base64_decode(
            '/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgNDRgyIRwhMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjL/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAn/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAGfAP/EABQQAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQEAAQUCf//EABQRAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQMBAT8Bf//EABQRAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQIBAT8Bf//Z'
        ));

        try {
            $owner = User::query()
                ->whereIn('user_role_id', [3, 4])
                ->where('status', User::STATUS_GRANTED)
                ->first();
            $other = User::query()
                ->whereIn('user_role_id', [3, 4])
                ->where('status', User::STATUS_GRANTED)
                ->where('id', '!=', $owner?->id)
                ->first();
        } catch (\Throwable $e) {
            $this->markTestSkipped('MongoDB unavailable: '.$e->getMessage());
        }

        if (! $owner || ! $other) {
            $this->markTestSkipped('Need at least two granted student/staff users.');
        }

        $owner->update(['email_verified_at' => now()]);
        $other->update(['email_verified_at' => now()]);

        $log = ViolationLog::query()->create([
            'user_id' => $owner->id,
            'violator_name' => $owner->name,
            'plate_number' => $owner->plate_number ?? 'TEST123',
            'violation_type' => 'Test',
            'description' => 'Evidence auth test',
            'evidence_photo' => $storedPath,
            'evidence_photos' => [$storedPath],
            'status' => 'Active',
            'created_at' => now(),
        ]);

        $id = (string) $log->getKey();

        $this->actingAs($owner)
            ->get(route('user.violations.evidence', ['id' => $id, 'index' => 0]))
            ->assertOk();

        $denied = $this->actingAs($other->fresh())
            ->get(route('user.violations.evidence', ['id' => $id, 'index' => 0]));

        $this->assertNotSame(200, $denied->getStatusCode(), 'Other users must not access foreign evidence.');
        $this->assertContains($denied->getStatusCode(), [403, 302, 401]);

        $this->actingAs($owner)
            ->get(route('user.violations'))
            ->assertOk()
            ->assertSee('My Violations')
            ->assertSee('View Evidence', false);

        Storage::disk('public')->delete($storedPath);
        ViolationLog::query()->where('_id', $log->getKey())->delete();
    }

    public function test_portal_pages_include_evidence_viewer_markup(): void
    {
        $admin = $this->seedUserOrSkip('admin@my.cspc.edu.ph');

        $this->actingAs($admin)
            ->get(route('admin.violations'))
            ->assertOk()
            ->assertSee('violation-evidence-modal', false)
            ->assertSee('data-violation-evidence-open', false)
            ->assertSee('No Evidence Available', false);
    }

    public function test_shell_includes_single_sidebar_toggle_and_evidence_modal(): void
    {
        $admin = $this->seedUserOrSkip('admin@my.cspc.edu.ph');

        $html = $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="portal-menu-btn"', $html);
        $this->assertStringNotContainsString('id="portal-sidebar-edge-toggle"', $html);
        $this->assertSame(1, substr_count($html, 'id="portal-menu-btn"'));
        $this->assertStringContainsString('id="violation-evidence-modal"', $html);
    }
}
