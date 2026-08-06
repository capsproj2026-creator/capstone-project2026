<?php

namespace App\Mail;

use App\Support\ViolationEvidence;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

/**
 * Sent when a guard or AI records a traffic/parking violation.
 */
class VehicleViolationMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  list<string>  $evidencePaths
     */
    public function __construct(
        public string $plateNumber,
        public string $violationType,
        public ?string $description = null,
        public Carbon|string|null $occurredAt = null,
        public ?string $location = null,
        public ?string $reportedBy = null,
        public array $evidencePaths = [],
        public ?string $remarks = null,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'ALERT: Campus Traffic / Parking Violation',
        );
    }

    public function content(): Content
    {
        $occurred = $this->occurredAt
            ? Carbon::parse($this->occurredAt)
            : now();

        $cidMap = [];
        foreach ($this->evidencePaths as $index => $path) {
            if (ViolationEvidence::absolutePath($path)) {
                $cidMap[$index] = 'violation-evidence-'.$index;
            }
        }

        return new Content(
            view: 'emails.violation',
            with: [
                'plateNumber' => $this->plateNumber,
                'violationType' => $this->violationType,
                'description' => $this->description,
                'remarks' => $this->remarks ?: $this->description,
                'location' => $this->location ?: 'Campus',
                'reportedBy' => $this->reportedBy ?: 'Campus Security',
                'occurredDate' => $occurred->timezone(config('app.timezone', 'Asia/Manila'))->format('F j, Y'),
                'occurredTime' => $occurred->timezone(config('app.timezone', 'Asia/Manila'))->format('g:i A'),
                'hasEvidence' => $cidMap !== [],
                'evidenceCids' => array_values($cidMap),
                'evidencePublicUrls' => array_values(array_filter(array_map(
                    fn (string $path) => ViolationEvidence::publicUrl($path),
                    $this->evidencePaths
                ))),
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $attachments = [];

        foreach ($this->evidencePaths as $index => $path) {
            $absolute = ViolationEvidence::absolutePath($path);
            if (! $absolute) {
                continue;
            }

            $mime = @mime_content_type($absolute) ?: 'image/jpeg';

            $attachments[] = Attachment::fromPath($absolute)
                ->as('violation-evidence-'.($index + 1).'.'.(pathinfo($absolute, PATHINFO_EXTENSION) ?: 'jpg'))
                ->withMime($mime);
        }

        return $attachments;
    }
}
