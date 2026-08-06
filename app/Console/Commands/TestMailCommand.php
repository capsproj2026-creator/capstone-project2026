<?php

namespace App\Console\Commands;

use App\Mail\RegistrationStatusMail;
use App\Mail\VehicleViolationMail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class TestMailCommand extends Command
{
    protected $signature = 'mail:test
                            {email : Recipient email address}
                            {--type=registration : Email to send: registration or violation}';

    protected $description = 'Send a test email through Brevo SMTP (avoids Tinker paste issues)';

    public function handle(): int
    {
        $email = $this->argument('email');
        $type = $this->option('type');

        $this->info('Mail driver: '.config('mail.default'));
        $this->info('SMTP host: '.config('mail.mailers.smtp.host'));
        $this->info('SMTP user: '.config('mail.mailers.smtp.username'));
        $this->newLine();

        try {
            if ($type === 'violation') {
                Mail::to($email)->send(new VehicleViolationMail(
                    plateNumber: 'ABC-1234',
                    violationType: 'No Parking Zone',
                    description: 'Vehicle parked outside the designated stall near Gate 1.',
                    occurredAt: now(),
                    location: 'Gate 1 Parking',
                    reportedBy: 'Campus Security (Test)',
                    evidencePaths: [],
                    remarks: 'Vehicle parked outside the designated stall near Gate 1.',
                ));
                $this->info('Sent VehicleViolationMail to '.$email);
            } else {
                Mail::to($email)->send(new RegistrationStatusMail('Juan Dela Cruz', 'Approved'));
                $this->info('Sent RegistrationStatusMail to '.$email);
            }

            $this->newLine();
            $this->comment('Check the inbox (and spam folder) for the test message.');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Email failed: '.$e->getMessage());
            $this->newLine();
            $this->warn('Verify in Brevo → SMTP & API: login email + SMTP key match .env exactly.');
            $this->warn('Run: php artisan config:clear');

            return self::FAILURE;
        }
    }
}
