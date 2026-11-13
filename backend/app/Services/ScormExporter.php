<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Support\Facades\File;
use ZipArchive;

class ScormExporter
{
    public function __construct(private PublicUrlFetcher $fetcher) {}

    public function export(Project $project): string
    {
        $module = $project->modules()->where('status', 'generated')->first();
        abort_unless($module && ! empty($module->module_json['screens']), 422, 'Generate a course before exporting.');
        $player = config('scorm.player_path');
        abort_unless(is_file($player.'/scorm.html'), 503, 'The export player has not been built.');
        $directory = storage_path('app/private/scorm-exports');
        File::ensureDirectoryExists($directory, 0700);
        $path = tempnam($directory, 'course_');
        if ($path === false) {
            throw new \RuntimeException('Could not create export file.');
        }
        $zip = new ZipArchive;
        $opened = false;
        try {
            if ($zip->open($path, ZipArchive::OVERWRITE | ZipArchive::CREATE) !== true) {
                throw new \RuntimeException('Could not open export archive.');
            }
            $opened = true;
            $files = ['module.json'];
            foreach (File::allFiles($player) as $file) {
                if ($file->isLink()) {
                    continue;
                }
                $relative = $file->getRelativePathname();
                $name = $relative === 'scorm.html' ? 'index.html' : $relative;
                if (! $zip->addFile($file->getPathname(), $name)) {
                    throw new \RuntimeException('Could not add player asset.');
                }
                $files[] = $name;
            }
            $assets = [];
            $totalBytes = 0;
            $localize = function (string $url) use (&$assets, &$totalBytes, &$files, $zip): string {
                if (isset($assets[$url])) {
                    return $assets[$url];
                }
                abort_if(count($assets) >= 100, 422, 'The course has too many media assets to export.');
                $bytes = $this->fetcher->fetch($url, 20 * 1024 * 1024);
                $totalBytes += strlen($bytes);
                abort_if($totalBytes > 100 * 1024 * 1024, 422, 'Course media exceeds the 100 MB export limit.');
                $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
                $extension = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif',
                    'image/webp' => 'webp', 'image/avif' => 'avif', 'video/mp4' => 'mp4',
                    'video/webm' => 'webm', 'video/quicktime' => 'mov'][$mime] ?? null;
                abort_unless($extension, 422, 'Course media is not a supported image or video.');
                $name = 'media/'.hash('sha256', $url).'.'.$extension;
                if (! $zip->addFromString($name, $bytes)) {
                    throw new \RuntimeException('Could not add course media.');
                }
                $files[] = $name;

                return $assets[$url] = './'.$name;
            };
            $json = $module->module_json;
            if (! empty($json['meta']['cover_image'])) {
                $json['meta']['cover_image'] = $localize($json['meta']['cover_image']);
            }
            foreach ($json['screens'] as &$screen) {
                if (! empty($screen['image']['url'])) {
                    $screen['image']['url'] = $localize($screen['image']['url']);
                }
                foreach ($screen['interactions'] ?? [] as $i => $interaction) {
                    if (($interaction['kind'] ?? '') === 'video' && ! empty($interaction['url'])) {
                        $screen['interactions'][$i]['url'] = $localize($interaction['url']);
                    }
                    foreach ($interaction['items'] ?? [] as $j => $item) {
                        if (! empty($item['image']['url'])) {
                            $screen['interactions'][$i]['items'][$j]['image']['url'] = $localize($item['image']['url']);
                        }
                    }
                }
            }
            unset($screen);
            $zip->addFromString('module.json', json_encode($json, JSON_THROW_ON_ERROR));
            $zip->addFromString('imsmanifest.xml', $this->manifest($project->title, $files));
            if (! $zip->close()) {
                throw new \RuntimeException('Could not finish course archive.');
            }
            $opened = false;

            return $path;
        } catch (\Throwable $e) {
            if ($opened) {
                $zip->close();
            }
            File::delete($path);
            throw $e;
        }
    }

    private function manifest(string $title, array $files): string
    {
        $escape = fn ($value) => htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $title = $escape($title);
        $fileElements = implode('', array_map(fn ($file) => '<file href="'.$escape($file).'"/>', $files));

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<manifest identifier="wbt" version="1.0" xmlns="http://www.imsproject.org/xsd/imscp_rootv1p1p2" xmlns:adlcp="http://www.adlnet.org/xsd/adlcp_rootv1p2">
  <metadata><schema>ADL SCORM</schema><schemaversion>1.2</schemaversion></metadata>
  <organizations default="ORG1"><organization identifier="ORG1"><title>{$title}</title><item identifier="ITEM1" identifierref="RES1"><title>{$title}</title></item></organization></organizations>
  <resources><resource identifier="RES1" type="webcontent" adlcp:scormtype="sco" href="index.html">{$fileElements}</resource></resources>
</manifest>
XML;
    }
}
