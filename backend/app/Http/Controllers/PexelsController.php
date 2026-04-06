<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class PexelsController extends Controller
{
    public function search(Request $request)
    {
        $q    = $request->query('q', '');
        $page = (int) $request->query('page', 1);

        $res = Http::withHeaders(['Authorization' => config('services.pexels.api_key', '')])
            ->timeout(10)
            ->get('https://api.pexels.com/v1/search', [
                'query'       => $q,
                'page'        => $page,
                'per_page'    => 12,
                'orientation' => 'landscape',
            ]);

        $data        = $res->json();
        $totalPages  = (int) ceil(($data['total_results'] ?? 0) / 12);

        // Normalise to a shape the frontend can consume uniformly
        $photos = array_map(fn($p) => [
            'id'              => (string) $p['id'],
            'url'             => $p['src']['large'] ?? $p['src']['original'],
            'thumb'           => $p['src']['medium'] ?? $p['src']['original'],
            'photographer'    => $p['photographer'],
            'photographer_url'=> $p['photographer_url'] ?? 'https://www.pexels.com',
            'alt'             => $p['alt'] ?? '',
        ], $data['photos'] ?? []);

        return response()->json([
            'photos'       => $photos,
            'total_pages'  => $totalPages,
            'page'         => $page,
        ]);
    }
}
