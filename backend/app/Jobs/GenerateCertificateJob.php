<?php

namespace App\Jobs;

use App\Mail\CertificateMail;
use App\Models\InviteProgress;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class GenerateCertificateJob implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly string $inviteId) {}

    public function handle(): void
    {
        $progress = InviteProgress::with(['invite.project', 'invite.invitedBy'])
            ->where('invite_id', $this->inviteId)
            ->firstOrFail();

        $verificationCode = strtoupper(substr(
            hash('sha256', $progress->invite->token . $progress->completed_at->toIso8601String()),
            0, 12
        ));

        $pdf = Pdf::loadView('certificates.completion', [
            'viewer_name'       => $progress->viewer_name ?? $progress->invite->email,
            'course_title'      => $progress->invite->project->title,
            'completed_at'      => $progress->completed_at->format('F j, Y'),
            'verification_code' => $verificationCode,
        ])->setPaper('a4', 'landscape');

        $filename = 'certificates/' . $this->inviteId . '.pdf';
        Storage::disk('local')->put($filename, $pdf->output());

        $progress->update(['certificate_path' => $filename]);

        Mail::to($progress->invite->email)->send(new CertificateMail($progress));
    }
}
