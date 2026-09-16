<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Retries the "verify your email" notification for accounts that registered
 * while offline/SMTP-unreachable (see RegisterController::completeRegistration).
 * Uses the app's existing Notifiable::sendEmailVerificationNotification() —
 * no new mailer/queue system is introduced.
 */
class RetryPendingVerificationEmailsCommand extends Command
{
    protected $signature = 'email:retry-verification';

    protected $description = 'Resend verification emails that failed to send at registration time (e.g. offline)';

    public function handle(): int
    {
        $users = User::query()
            ->where('verification_email_pending', true)
            ->whereNull('email_verified_at')
            ->limit(50)
            ->get();

        if ($users->isEmpty()) {
            $this->line('[email] no pending verification emails to retry');

            return self::SUCCESS;
        }

        foreach ($users as $user) {
            /** @var User $user */
            try {
                $user->sendEmailVerificationNotification();
                User::withoutSyncStamping(fn () => $user->forceFill(['verification_email_pending' => false])->save());
                $this->info("[email] verification resent to user #{$user->id}");
            } catch (\Throwable $e) {
                report($e);
                $this->warn("[email] still failing for user #{$user->id}: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
