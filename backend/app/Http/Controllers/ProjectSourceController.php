<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Project;
use Smalot\PdfParser\Parser as PdfParser;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use PhpOffice\PhpPresentation\IOFactory as PresentationIOFactory;

class ProjectSourceController extends Controller
{
    private const MAX_CHARS = 50000;

    public function store(Request $request, string $projectId)
    {
        $request->validate([
            'source_type' => 'required|in:file,url',
            'file' => 'required_if:source_type,file|file|mimes:pdf,docx,pptx|max:10240',
            'url'  => 'required_if:source_type,url|url',
        ]);

        $project = Project::where('id', $projectId)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $sourceType = $request->input('source_type');

        [$text, $sourceUrl] = $sourceType === 'file'
            ? $this->extractFromFile($request)
            : $this->extractFromUrl($request->input('url'));

        if (mb_strlen($text) > self::MAX_CHARS) {
            $text = mb_substr($text, 0, self::MAX_CHARS);
        }

        $text = trim($text);
        $charCount = mb_strlen($text);

        $project->update([
            'source_type'            => $sourceType,
            'source_url'             => $sourceUrl,
            'source_text'            => $text,
            'source_character_count' => $charCount,
        ]);

        return response()->json([
            'source_type'      => $sourceType,
            'source_url'       => $sourceUrl,
            'character_count'  => $charCount,
            'truncated'        => $charCount === self::MAX_CHARS,
        ]);
    }

    private function extractFromFile(Request $request): array
    {
        $file = $request->file('file');
        $mime = $file->getMimeType();
        $path = $file->getRealPath();

        try {
            $text = match (true) {
                str_contains($mime, 'pdf')          => $this->extractPdf($path),
                str_contains($mime, 'wordprocessingml'),
                str_contains($mime, 'msword'),
                $file->getClientOriginalExtension() === 'docx' => $this->extractDocx($path),
                str_contains($mime, 'presentationml'),
                str_contains($mime, 'powerpoint'),
                $file->getClientOriginalExtension() === 'pptx' => $this->extractPptx($path),
                default => throw new \RuntimeException("Unsupported file type: {$mime}"),
            };
        } finally {
            // Delete the upload immediately after extraction
            if (file_exists($path)) {
                @unlink($path);
            }
        }

        return [$text, null];
    }

    private function extractPdf(string $path): string
    {
        $parser = new PdfParser();
        $pdf    = $parser->parseFile($path);
        return $pdf->getText();
    }

    private function extractDocx(string $path): string
    {
        $phpWord  = WordIOFactory::load($path);
        $sections = $phpWord->getSections();
        $lines    = [];

        foreach ($sections as $section) {
            foreach ($section->getElements() as $element) {
                if (method_exists($element, 'getText')) {
                    $lines[] = $element->getText();
                } elseif (method_exists($element, 'getElements')) {
                    foreach ($element->getElements() as $child) {
                        if (method_exists($child, 'getText')) {
                            $lines[] = $child->getText();
                        }
                    }
                }
            }
        }

        return implode("\n", array_filter($lines));
    }

    private function extractPptx(string $path): string
    {
        $presentation = PresentationIOFactory::load($path);
        $lines        = [];

        foreach ($presentation->getAllSlides() as $slide) {
            foreach ($slide->getShapeCollection() as $shape) {
                if (method_exists($shape, 'getPlainText')) {
                    $lines[] = $shape->getPlainText();
                } elseif (method_exists($shape, 'getText')) {
                    $lines[] = $shape->getText();
                }
            }
        }

        return implode("\n", array_filter($lines));
    }

    private function extractFromUrl(string $url): array
    {
        $response = Http::timeout(15)->get($url);

        if (!$response->successful()) {
            abort(422, "Could not fetch URL (HTTP {$response->status()})");
        }

        $html = $response->body();

        // Strip scripts and styles before extracting text
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html);
        $html = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $html);
        $text = strip_tags($html);

        // Collapse whitespace
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        return [$text, $url];
    }
}
