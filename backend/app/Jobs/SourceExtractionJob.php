<?php

namespace App\Jobs;

use Anthropic\Client as AnthropicClient;
use App\Models\Project;
use App\Services\ModerationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser as PdfParser;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use PhpOffice\PhpPresentation\IOFactory as PresentationIOFactory;

class SourceExtractionJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;
    public int $tries   = 1;

    private const MAX_CHARS = 50000;

    public function __construct(
        public readonly string $projectId,
        public readonly string $sourceType,  // 'file' | 'url'
        public readonly ?string $tempPath,   // R2 path for file uploads
        public readonly ?string $url,        // for URL sources
        public readonly ?string $fileExt,    // original extension for file sources
    ) {}

    public function handle(ModerationService $moderation): void
    {
        $project = Project::findOrFail($this->projectId);

        try {
            [$text, $sourceUrl] = $this->sourceType === 'file'
                ? $this->extractFromTempFile()
                : $this->extractFromUrl();

            if (mb_strlen($text) > self::MAX_CHARS) {
                $text = mb_substr($text, 0, self::MAX_CHARS);
            }
            $text     = trim($text);
            $charCount = mb_strlen($text);

            $result = $moderation->check($text);
            if (!$result->safe) {
                $project->update([
                    'source_status' => 'failed',
                    'source_error'  => 'This content cannot be used as course material: ' . ($result->reason ?? 'content policy violation.'),
                ]);
                return;
            }

            $project->update([
                'source_text'            => $text,
                'source_url'             => $sourceUrl,
                'source_character_count' => $charCount,
                'source_status'          => 'ready',
                'source_error'           => null,
                'source_temp_path'       => null,
            ]);

            Log::info("SourceExtractionJob succeeded for project {$this->projectId}: {$charCount} chars");
        } catch (\Throwable $e) {
            $project->update([
                'source_status' => 'failed',
                'source_error'  => $e->getMessage(),
            ]);
            Log::error("SourceExtractionJob failed for project {$this->projectId}: " . $e->getMessage());
        } finally {
            // Clean up temp file from R2
            if ($this->tempPath) {
                Storage::disk('r2')->delete($this->tempPath);
            }
        }
    }

    private function extractFromTempFile(): array
    {
        $tmpLocal = tempnam(sys_get_temp_dir(), 'wbt_src_') . '.' . $this->fileExt;

        try {
            $contents = Storage::disk('r2')->get($this->tempPath);
            if (!$contents) {
                throw new \RuntimeException('Could not retrieve source file from storage.');
            }
            file_put_contents($tmpLocal, $contents);

            $text = match ($this->fileExt) {
                'pdf'  => $this->extractPdf($tmpLocal),
                'docx' => $this->extractDocx($tmpLocal),
                'pptx' => $this->extractPptx($tmpLocal),
                default => throw new \RuntimeException("Unsupported file type: {$this->fileExt}"),
            };
        } finally {
            if (file_exists($tmpLocal)) @unlink($tmpLocal);
        }

        return [$text, null];
    }

    private function extractFromUrl(): array
    {
        $response = Http::timeout(15)
            ->withHeaders([
                'User-Agent'      => 'Mozilla/5.0 (compatible; WBTForge/1.0; +https://wbtforge.com)',
                'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.5',
            ])
            ->get($this->url);

        if (!$response->successful()) {
            throw new \RuntimeException("Could not fetch URL (HTTP {$response->status()})");
        }

        $html = $response->body();
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html);
        $html = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $html);
        $text = strip_tags($html);
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        return [$text, $this->url];
    }

    private function extractPdf(string $path): string
    {
        try {
            $parser = new PdfParser();
            $text   = $parser->parseFile($path)->getText();
            if (trim($text) !== '') return $text;
            Log::info('PDF returned empty text — falling back to Claude');
        } catch (\Throwable $e) {
            Log::info("PDF text extraction failed ({$e->getMessage()}) — falling back to Claude");
        }

        return $this->extractPdfViaClaude($path);
    }

    private function extractPdfViaClaude(string $path): string
    {
        $pdfPath  = $path;
        $tempPath = null;

        if ($this->commandExists('qpdf')) {
            $tmp  = tempnam(sys_get_temp_dir(), 'wbt_pdf_') . '.pdf';
            $src  = escapeshellarg($path);
            $dst  = escapeshellarg($tmp);
            exec("qpdf {$src} --pages . 1-100 -- {$dst} 2>/dev/null", $out, $code);

            if ($code === 0 && file_exists($tmp) && filesize($tmp) > 0) {
                $pdfPath  = $tmp;
                $tempPath = $tmp;
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
                        ['type' => 'document', 'source' => ['type' => 'base64', 'media_type' => 'application/pdf', 'data' => $base64]],
                        ['type' => 'text', 'text' => 'Extract all readable text content from this PDF. Return only the extracted text, preserving structure. No commentary.'],
                    ],
                ]],
                model: $model,
            );

            $text = $response->content[0]->text ?? '';
            if (trim($text) === '') {
                throw new \RuntimeException('Could not extract text from this PDF. The file may be encrypted or image-only without OCR support.');
            }

            return $text;
        } catch (\Anthropic\Core\Exceptions\BadRequestException $e) {
            if (str_contains($e->getMessage(), 'maximum of 100 PDF pages')) {
                throw new \RuntimeException('This PDF exceeds 100 pages. Please split the document or provide a shorter version.');
            }
            throw $e;
        } finally {
            if ($tempPath && file_exists($tempPath)) @unlink($tempPath);
        }
    }

    private function extractDocx(string $path): string
    {
        $phpWord  = WordIOFactory::load($path);
        $lines    = [];

        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                if (method_exists($element, 'getText')) {
                    $lines[] = $element->getText();
                } elseif (method_exists($element, 'getElements')) {
                    foreach ($element->getElements() as $child) {
                        if (method_exists($child, 'getText')) $lines[] = $child->getText();
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
                if (method_exists($shape, 'getPlainText')) $lines[] = $shape->getPlainText();
                elseif (method_exists($shape, 'getText')) $lines[] = $shape->getText();
            }
        }

        return implode("\n", array_filter($lines));
    }

    private function commandExists(string $command): bool
    {
        exec('which ' . escapeshellarg($command) . ' 2>/dev/null', $out, $code);
        return $code === 0;
    }
}
