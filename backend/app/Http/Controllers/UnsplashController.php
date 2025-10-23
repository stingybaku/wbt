<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class UnsplashController extends Controller
{
    public function search(Request $request)
    {
        $q = $request->query('q', '');     // search query
        $page = $request->query('page', 1);

        $res = Http::get('https://api.unsplash.com/search/photos', [
            'query' => $q,
            'page' => $page,
            'per_page' => 12,
            'client_id' => config('services.unsplash.access_key'),
        ]);

        return response()->json($res->json());
    }
}
