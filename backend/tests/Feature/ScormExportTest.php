<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\PublicUrlFetcher;
use App\Services\ScormExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\Support\Fixtures;
use Tests\TestCase;

class ScormExportTest extends TestCase
{
    use Fixtures, RefreshDatabase;

    private string $player;

    protected function setUp(): void
    {
        parent::setUp();
        $this->player = sys_get_temp_dir().'/wbt-player-'.bin2hex(random_bytes(6));
        File::ensureDirectoryExists($this->player.'/assets');
        File::put($this->player.'/scorm.html', '<script src="./assets/player.js"></script>');
        File::put($this->player.'/assets/player.js', '/* player fixture */');
        config(['scorm.player_path' => $this->player, 'scorm.enabled' => false]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->player);
        parent::tearDown();
    }

    public function test_export_is_gated_and_checks_project_ownership(): void
    {
        $owner = User::factory()->create();
        $project = $this->project($owner);
        $this->course($project);
        $url = "/api/projects/{$project->id}/scorm";
        $this->postJson($url)->assertUnauthorized();
        $this->actingAs(User::factory()->create())->postJson($url)->assertNotFound();
        $this->actingAs($owner)->postJson($url)->assertStatus(503);
    }

    public function test_zip_includes_player_media_and_escaped_manifest_and_is_removed_after_download(): void
    {
        $owner = User::factory()->create();
        $project = $this->project($owner);
        $project->update(['title' => 'Safety & <Training>']);
        $module = $this->course($project);
        $json = $module->module_json;
        $json['screens'][0]['image'] = ['url' => 'https://example.com/image.png', 'alt_text' => 'image'];
        $module->update(['module_json' => $json]);
        $fetcher = \Mockery::mock(PublicUrlFetcher::class);
        $fetcher->shouldReceive('fetch')->once()->with('https://example.com/image.png', 20 * 1024 * 1024)
            ->andReturn(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a9ZkAAAAASUVORK5CYII='));
        $this->app->instance(PublicUrlFetcher::class, $fetcher);
        config(['scorm.enabled' => true]);
        $response = $this->actingAs($owner)->postJson("/api/projects/{$project->id}/scorm")->assertOk()->assertDownload('training.zip');
        $path = $response->baseResponse->getFile()->getPathname();
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($path));
        $manifest = new \DOMDocument;
        $this->assertTrue($manifest->loadXML($zip->getFromName('imsmanifest.xml')));
        $this->assertSame('Safety & <Training>', $manifest->getElementsByTagName('title')->item(0)->textContent);
        $this->assertNotFalse($zip->getFromName('index.html'));
        $this->assertNotFalse($zip->getFromName('assets/player.js'));
        $packed = json_decode($zip->getFromName('module.json'), true);
        $asset = substr($packed['screens'][0]['image']['url'], 2);
        $this->assertNotFalse($zip->getFromName($asset));
        $zip->close();
        ob_start();
        try {
            $response->baseResponse->sendContent();
        } finally {
            ob_end_clean();
        }
        $this->assertFileDoesNotExist($path);
    }

    public function test_failed_media_download_removes_partial_archive(): void
    {
        $project = $this->project(User::factory()->create());
        $module = $this->course($project);
        $json = $module->module_json;
        $json['screens'][0]['image'] = ['url' => 'http://127.0.0.1/', 'alt_text' => 'image'];
        $module->update(['module_json' => $json]);
        $directory = storage_path('app/private/scorm-exports');
        File::ensureDirectoryExists($directory);
        $before = count(File::files($directory));
        try {
            app(ScormExporter::class)->export($project);
            $this->fail('Expected blocked URL');
        } catch (\Illuminate\Validation\ValidationException) {
            $this->assertCount($before, File::files($directory));
        }
    }
}
