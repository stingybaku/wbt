<?php

namespace App\Http\Controllers;

use Anthropic\Client as AnthropicClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Project;
use App\Services\ModerationService;
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

        $moderation = app(ModerationService::class)->check($text);
        if (!$moderation->safe) {
            abort(422, 'This content cannot be used as course material: ' . ($moderation->reason ?? 'content policy violation.'));
        }

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
        try {
            $parser = new PdfParser();
            $pdf    = $parser->parseFile($path);
            $text   = $pdf->getText();

            if (trim($text) !== '') {
                return $text;
            }

            // Empty text = likely a scanned or image-only PDF; fall through to Claude
            Log::info('PDF parsed but returned empty text — falling back to Claude document API');
        } catch (\Throwable $e) {
            Log::info("PDF text extraction failed ({$e->getMessage()}) — falling back to Claude document API");
        }

        return $this->extractPdfViaClaude($path);
    }

    private function extractPdfViaClaude(string $path): string
    {
        // Claude accepts a maximum of 100 PDF pages. Trim with qpdf if available.
        $pdfPath  = $path;
        $tempPath = null;

        if ($this->commandExists('qpdf')) {
            $tmp      = tempnam(sys_get_temp_dir(), 'wbt_pdf_') . '.pdf';
            $src      = escapeshellarg($path);
            $dst      = escapeshellarg($tmp);
            exec("qpdf {$src} --pages . 1-100 -- {$dst} 2>/dev/null", $out, $code);

            if ($code === 0 && file_exists($tmp) && filesize($tmp) > 0) {
                $pdfPath  = $tmp;
                $tempPath = $tmp;
                Log::info('PDF trimmed to 100 pages via qpdf before Claude extraction');
            } else {
                @unlink($tmp);
            }
        }

        try {
            $base64  = base64_encode(file_get_contents($pdfPath));
            $client  = new AnthropicClient(apiKey: config('services.anthropic.api_key'));
            $model   = config('services.anthropic.model', 'claude-sonnet-4-6');

            $response = $client->messages->create(
                maxTokens: 4096,
                messages: [[
                    'role'    => 'user',
                    'content' => [
                        [
                            'type'   => 'document',
                            'source' => [
                                'type'       => 'base64',
                                'media_type' => 'application/pdf',
                                'data'       => $base64,
                            ],
                        ],
                        [
                            'type' => 'text',
                            'text' => 'Extract all readable text content from this PDF. Return only the extracted text, preserving the structure as much as possible. Do not add any commentary or explanation.',
                        ],
                    ],
                ]],
                model: $model,
            );

            $text = $response->content[0]->text ?? '';

            if (trim($text) === '') {
                abort(422, 'Could not extract text from this PDF. The file may be encrypted with a user password. Please use an unprotected PDF or provide the source content another way.');
            }

            return $text;
        } catch (\Anthropic\Core\Exceptions\BadRequestException $e) {
            if (str_contains($e->getMessage(), 'maximum of 100 PDF pages')) {
                abort(422, 'This PDF exceeds 100 pages and qpdf is not available to trim it. Please split the document or provide a shorter version (100 pages or fewer).');
            }
            throw $e;
        } finally {
            if ($tempPath && file_exists($tempPath)) {
                @unlink($tempPath);
            }
        }
    }

    private function commandExists(string $command): bool
    {
        exec('which ' . escapeshellarg($command) . ' 2>/dev/null', $out, $code);
        return $code === 0;
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
        $response = Http::timeout(15)
            ->withHeaders([
                'User-Agent'      => 'Mozilla/5.0 (compatible; WBTForge/1.0; +https://wbtforge.com)',
                'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.5',
            ])
            ->get($url);

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
