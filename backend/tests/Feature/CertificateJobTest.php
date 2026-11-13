<?php

namespace Tests\Feature;

use App\Jobs\GenerateCertificateJob;
use App\Mail\CertificateMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Fixtures;
use Tests\TestCase;

class CertificateJobTest extends TestCase
{
    use Fixtures, RefreshDatabase;

    public function test_repeated_job_delivery_creates_one_certificate_and_one_mail(): void
    {
        Mail::fake();
        Notification::fake();
        Storage::fake('local');
        $project = $this->project(User::factory()->create());
        $invite = $this->invite($project);
        $job = new GenerateCertificateJob($invite->id);
        $job->handle();
        Mail::assertNothingQueued();
        $invite->progress->update(['completed_at' => now()]);
        $job->handle();
        $job->handle();
        Mail::assertQueued(CertificateMail::class, 1);
        Storage::disk('local')->assertExists('certificates/'.$invite->id.'.pdf');
    }
}
