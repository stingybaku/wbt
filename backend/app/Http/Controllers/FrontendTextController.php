<?php

namespace App\Http\Controllers;

use App\Models\FrontendText;
use Illuminate\Http\Request;

class FrontendTextController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'route_key' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9._-]+$/'],
            'include_inactive' => ['nullable', 'boolean'],
        ]);

        $textsQuery = FrontendText::query()
            ->where('route_key', $validated['route_key']);

        if (!($validated['include_inactive'] ?? false)) {
            $textsQuery->where('is_active', true);
        }

        $texts = $textsQuery
            ->orderBy('text_key')
            ->get(['text_key', 'value'])
            ->mapWithKeys(fn (FrontendText $frontendText) => [$frontendText->text_key => $frontendText->value])
            ->all();

        return response()->json([
            'route_key' => $validated['route_key'],
            'texts' => $texts,
        ]);
    }
}
