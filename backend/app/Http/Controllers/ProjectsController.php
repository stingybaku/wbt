<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\Project;

class ProjectsController extends Controller
{
    public function index(Request $request)
    {
        $projects = Project::where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            // Only the three summary values are read from module_json, so they
            // are extracted in SQL. Selecting the whole document here meant the
            // dashboard transferred and decoded every generated course in full
            // just to render a summary line and a screen count.
            ->with(['modules' => fn ($q) => $q->select(array_merge(
                ['project_id', 'status', 'cover_image_url'],
                $this->moduleSummaryColumns()
            ))])
            ->withCount(['invites' => fn ($q) => $q->whereNull('revoked_at')->where('expires_at', '>', now())])
            ->get()
            ->map(function ($project) {
                $module = $project->modules->first();
                $data = $project->toArray();
                unset($data['modules']);

                $data['cover_image_url']  = $module?->cover_image_url;
                $data['module_status']    = $module?->status;
                $data['source_summary']   = $module?->meta_summary ?? $module?->meta_source_summary;
                $data['duration_minutes'] = $this->asIntOrNull($module?->meta_duration_minutes);
                $data['screen_count']     = $this->asIntOrNull($module?->screen_count);
                $data['invite_count']     = $project->invites_count;
                unset($data['invites_count']);

                return $data;
            });

        return response()->json(['projects' => $projects]);
    }

    /**
     * SQL expressions pulling the dashboard's summary fields out of module_json
     * without selecting the document itself. Each returns null when module_json
     * is null (a module still generating) or the key is absent, which is what
     * the frontend checks for.
     *
     * @return array<int, \Illuminate\Contracts\Database\Query\Expression>
     */
    private function moduleSummaryColumns(): array
    {
        return [
            DB::raw("module_json -> 'meta' ->> 'summary' as meta_summary"),
            DB::raw("module_json -> 'meta' ->> 'source_summary' as meta_source_summary"),
            DB::raw("module_json -> 'meta' ->> 'duration_minutes' as meta_duration_minutes"),
            DB::raw("case when json_typeof(module_json -> 'screens') = 'array'
                          then json_array_length(module_json -> 'screens')
                     end as screen_count"),
        ];
    }

    /**
     * ->> always yields text, and the frontend types these as number|null.
     */
    private function asIntOrNull(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string',
            'rights_attestation' => 'required|boolean'
        ]);

        $project = Project::create([
            'id' => 'proj_' . Str::uuid()->toString(),
            'title' => $data['title'],
            'rights_attestation' => $data['rights_attestation'],
            'user_id' => $request->user()->id,
        ]);

        return response()->json(['project' => $project], 201);
    }

    public function show(Request $request, string $id)
    {
        $project = Project::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->with(['modules' => fn ($q) => $q->select('project_id', 'status', 'cover_image_url')])
            ->firstOrFail();

        $module = $project->modules->first();
        $data = $project->toArray();
        unset($data['modules']);
        $data['cover_image_url'] = $module?->cover_image_url;
        $data['module_status']   = $module?->status;

        return response()->json(['project' => $data]);
    }
}
