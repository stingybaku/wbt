<?php

namespace App\Http\Controllers;

use App\Services\ScormExporter;

class ScormController extends Controller
{
    public function export(string $projectId, ScormExporter $exporter)
    {
        $project = $this->ownedProject($projectId);
        abort_unless(config('scorm.enabled'), 503, 'SCORM export is awaiting LMS validation.');

        return response()->download($exporter->export($project), 'training.zip')
            ->deleteFileAfterSend(true);
    }
}
