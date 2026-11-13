<?php

namespace App\Http\Controllers;

use App\Models\ModuleFlag;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ModuleFlagsController extends Controller
{
    public function store(Request $request, string $projectId)
    {
        $data = $request->validate([
            'target_id' => 'required|string',
            'target_type' => 'required|in:screen,interaction',
            'reason' => 'required|string',
            'notes' => 'nullable|string',
        ]);

        $project = $this->ownedProject($projectId);
        $flag = ModuleFlag::create([
            'id' => 'flag_'.Str::uuid()->toString(),
            'project_id' => $project->id,
            'target_id' => $data['target_id'],
            'target_type' => $data['target_type'],
            'reason' => $data['reason'],
            'notes' => $data['notes'] ?? null,
            'status' => 'open',
        ]);

        return response()->json([
            'project_id' => $projectId,
            'flag' => $flag,
        ], 201);
    }
}
