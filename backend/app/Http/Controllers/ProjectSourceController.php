<?php

namespace App\Http\Controllers;

use App\Jobs\SourceExtractionJob;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProjectSourceController extends Controller
{
    public function store(Request $request, string $projectId)
    {
        // Log raw PHP upload state before validation so errors are visible in Railway logs
        if ($request->input('source_type') === 'file') {
            $phpFile = $_FILES['file'] ?? null;
            Log::info('ProjectSourceController: incoming file upload', [
                'project_id'       => $projectId,
                'php_upload_error' => $phpFile['error'] ?? 'no $_FILES entry',
                'php_upload_size'  => $phpFile['size'] ?? null,
                'content_length'   => $request->server('CONTENT_LENGTH'),
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size'    => ini_get('post_max_size'),
            ]);
        }

        $request->validate([
            'source_type' => 'required|in:file,url',
            'file'        => 'required_if:source_type,file|file|mimes:pdf,docx,pptx|max:10240',
            'url'         => 'required_if:source_type,url|url',
        ]);

        $project = Project::where('id', $projectId)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $sourceType = $request->input('source_type');

        if ($sourceType === 'file') {
            $file    = $request->file('file');
            $ext     = strtolower($file->getClientOriginalExtension());
            $tmpPath = 'temp/sources/' . $projectId . '_' . Str::random(8) . '.' . $ext;

            Storage::disk('r2')->put($tmpPath, file_get_contents($file->getRealPath()), 'private');

            $project->update([
                'source_type'      => 'file',
                'source_status'    => 'extracting',
                'source_error'     => null,
                'source_temp_path' => $tmpPath,
            ]);

            SourceExtractionJob::dispatch($projectId, 'file', $tmpPath, null, $ext);
        } else {
            $url = $request->input('url');

            $project->update([
                'source_type'   => 'url',
                'source_url'    => $url,
                'source_status' => 'extracting',
                'source_error'  => null,
            ]);

            SourceExtractionJob::dispatch($projectId, 'url', null, $url, null);
        }

        return response()->json([
            'source_type'     => $sourceType,
            'source_url'      => $sourceType === 'url' ? $request->input('url') : null,
            'character_count' => null,
            'truncated'       => false,
            'status'          => 'extracting',
        ], 202);
    }
}
