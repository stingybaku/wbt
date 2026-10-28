<?php

namespace App\Console\Commands;

use App\Models\Module;
use App\Models\SourcedImage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class BackfillImagesToR2 extends Command
{
    protected $signature = 'images:backfill-r2 {--rewrite-modules : Also update module JSONs to use R2 URLs}';
    protected $description = 'Upload existing sourced images to Cloudflare R2 and optionally rewrite module JSONs';

    public function handle(): void
    {
        if (!config('filesystems.disks.r2.key')) {
            $this->error('R2 is not configured. Set CLOUDFLARE_R2_* env vars.');
            return;
        }

        $this->uploadSourcedImages();

        if ($this->option('rewrite-modules')) {
            $this->rewriteModules();
        }
    }

    private function uploadSourcedImages(): void
    {
        $images = SourcedImage::whereNull('stored_path')->get();
        $this->info("Uploading {$images->count()} images to R2...");
        $bar = $this->output->createProgressBar($images->count());

        foreach ($images as $image) {
            try {
                $response = Http::timeout(15)->get($image->url);
                if (!$response->successful()) {
                    $this->warn("\nFailed to download {$image->url}: HTTP {$response->status()}");
                    $bar->advance();
                    continue;
                }

                $contentType = $response->header('Content-Type') ?? 'image/jpeg';
                $ext = $this->extensionFromContentType($contentType);
                $path = "images/{$image->source}/{$image->external_id}.{$ext}";

                Storage::disk('r2')->put($path, $response->body(), [
                    'visibility'  => 'public',
                    'ContentType' => $contentType,
                ]);

                $image->update(['stored_path' => $path]);
            } catch (\Throwable $e) {
                $this->warn("\nFailed for image {$image->id}: " . $e->getMessage());
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Done uploading sourced images.');
    }

    private function rewriteModules(): void
    {
        // Build a lookup map: base URL (no query params) → R2 URL
        $baseUrlMap = SourcedImage::whereNotNull('stored_path')
            ->get()
            ->mapWithKeys(fn ($img) => [
                preg_replace('/\?.*$/', '', $img->url) => $img->resolvedUrl(),
            ])
            ->all();

        if (empty($baseUrlMap)) {
            $this->warn('No R2 URLs available to rewrite with.');
            return;
        }

        $modules = Module::where('status', 'generated')->whereNotNull('module_json')->get();
        $this->info("Rewriting {$modules->count()} module JSONs...");
        $rewritten = 0;

        foreach ($modules as $module) {
            $json = json_encode($module->module_json, JSON_UNESCAPED_SLASHES);

            // Replace each external URL (with any query string) with the R2 URL
            $updated = preg_replace_callback(
                '#https://images\.(pexels|unsplash)\.com/[^\s"]+#',
                function ($m) use ($baseUrlMap) {
                    $baseUrl = preg_replace('/\?.*$/', '', $m[0]);
                    return $baseUrlMap[$baseUrl] ?? $m[0];
                },
                $json
            );

            if ($updated !== $json) {
                $module->update(['module_json' => json_decode($updated, true)]);
                $rewritten++;
            }
        }

        $this->info("Rewrote {$rewritten} modules.");
    }

    private function extensionFromContentType(string $contentType): string
    {
        return match (true) {
            str_contains($contentType, 'png')  => 'png',
            str_contains($contentType, 'gif')  => 'gif',
            str_contains($contentType, 'webp') => 'webp',
            default                            => 'jpg',
        };
    }
}
