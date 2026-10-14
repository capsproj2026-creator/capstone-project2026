<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class RegistrationAlertService
{
    public function notifyAdmins(User $applicant, string $title, string $message): void
    {
        $admins = User::query()
            ->where('user_role_id', NavigationService::ROLE_ADMIN)
            ->where('status', User::STATUS_GRANTED)
            ->get();

        foreach ($admins as $admin) {
            Notification::query()->create([
                'user_id' => $admin->id,
                'sender_id' => $applicant->id,
                'title' => $title,
                'message' => $message,
                'type' => 'System',
                'is_read' => false,
                'created_at' => now(),
            ]);

            Cache::forget('layout.unread_notifications.'.$admin->id.'.'.NavigationService::ROLE_ADMIN);
        }
    }

    public function notifyRole(int $roleId, ?int $senderId, string $title, string $message, string $type = 'System'): void
    {
        $users = User::query()
            ->where('user_role_id', $roleId)
            ->where('status', User::STATUS_GRANTED)
            ->get();

        foreach ($users as $user) {
            Notification::query()->create([
                'user_id' => $user->id,
                'sender_id' => $senderId,
                'title' => $title,
                'message' => $message,
                'type' => $type,
                'is_read' => false,
                'created_at' => now(),
            ]);

            Cache::forget('layout.unread_notifications.'.$user->id.'.'.$roleId);
        }
    }
}
