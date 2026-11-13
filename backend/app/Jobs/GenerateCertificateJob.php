<?php

namespace App\Jobs;

use App\Mail\CertificateMail;
use App\Models\InviteProgress;
use App\Notifications\ViewerCompletedNotification;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class GenerateCertificateJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly string $inviteId) {}

    public function handle(): void
    {
        \Illuminate\Support\Facades\DB::transaction(function () {
            $progress = InviteProgress::with(['invite.project', 'invite.invitedBy'])
                ->where('invite_id', $this->inviteId)
                ->lockForUpdate()->firstOrFail();

            if (! $progress->completed_at || $progress->certificate_path) {
                return;
            }

            $verificationCode = strtoupper(substr(
                hash('sha256', $progress->invite->token.$progress->completed_at->toIso8601String()),
                0, 12
            ));

            $pdf = Pdf::loadView('certificates.completion', [
                'viewer_name' => $progress->viewer_name ?? $progress->invite->email,
                'course_title' => $progress->invite->project->title,
                'completed_at' => $progress->completed_at->format('F j, Y'),
                'verification_code' => $verificationCode,
            ])->setPaper('a4', 'landscape');

            $filename = 'certificates/'.$this->inviteId.'.pdf';
            if (! Storage::disk('local')->put($filename, $pdf->output())) {
                throw new \RuntimeException('Could not store certificate.');
            }

            $progress->update(['certificate_path' => $filename]);

            Mail::to($progress->invite->email)->queue((new CertificateMail($progress))->onConnection('database')->beforeCommit());

            $progress->invite->invitedBy->notify((new ViewerCompletedNotification($progress))->onConnection('database')->beforeCommit());
        }, 3);
    }
}
