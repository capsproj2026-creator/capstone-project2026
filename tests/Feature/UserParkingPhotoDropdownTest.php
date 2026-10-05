<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserParkingPhotoDropdownTest extends TestCase
{
    private ?User $user = null;

    protected function tearDown(): void
    {
        try {
            $this->user?->delete();
        } catch (\Throwable) {
        }

        parent::tearDown();
    }

    public function test_parking_area_photo_is_collapsed_in_its_own_dropdown(): void
    {
        try {
            $this->user = User::query()->create([
                'id' => ((int) (microtime(true) * 1000) + 11) % 2000000000,
                'fullname' => 'Parking Photo Tester',
                'email' => 'parking.photo.'.uniqid().'@example.com',
                'password' => Hash::make('Secret12!'),
                'user_role_id' => 3,
                'id_number' => 'PP'.substr((string) time(), -6),
                'status' => User::STATUS_GRANTED,
                'Gate_access' => User::GATE_ACCESS_GRANTED,
                'strike_count' => 0,
                'email_verified_at' => now(),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $this->markTestSkipped('MongoDB unavailable: '.$e->getMessage());
        }

        $html = $this->actingAs($this->user)
            ->get(route('user.parking'))
            ->assertOk()
            ->getContent();

        $photos = preg_match_all('/alt="[^"]* parking area"/', $html);
        if ($photos === 0) {
            $this->markTestSkipped('No parking-area photos configured for any zone.');
        }

        // Every photo sits inside its own closed dropdown (no "open" attribute).
        $this->assertSame($photos, preg_match_all('/<details class="group\/photo[^"]*" data-zone-photo>/', $html));
        $this->assertSame(0, preg_match_all('/<details[^>]*data-zone-photo[^>]*\sopen/', $html));
        $this->assertSame($photos, substr_count($html, 'Parking area photo'));

        $this->assertMatchesRegularExpression(
            '/data-zone-photo>.*?<summary.*?Parking area photo.*?<\/summary>.*?alt="[^"]* parking area"/s',
            $html
        );
    }
}
