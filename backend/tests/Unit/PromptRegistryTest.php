<?php

namespace Tests\Unit;

use App\Prompts\Prompt;
use App\Prompts\PromptRegistry;
use Tests\TestCase;

class PromptRegistryTest extends TestCase
{
    public function test_every_current_prompt_exists_on_disk(): void
    {
        $registry = new PromptRegistry();

        foreach (PromptRegistry::CURRENT as $key => $version) {
            $prompt = $registry->get($key);
            $this->assertSame("{$key}@{$version}", $prompt->id());
            $this->assertNotSame('', $prompt->system);
        }
    }

    public function test_unknown_prompt_or_version_fails_loudly(): void
    {
        $registry = new PromptRegistry();

        $this->expectException(\InvalidArgumentException::class);
        $registry->get(PromptRegistry::MODULE_GENERATION, 999);
    }

    public function test_render_fills_every_placeholder(): void
    {
        $prompt = new Prompt('t', 1, 'sys', "Title: {{title}}\nCount: {{ count }}");

        $this->assertSame("Title: Fire safety\nCount: 3", $prompt->render(['title' => 'Fire safety', 'count' => 3]));
    }

    public function test_render_rejects_missing_and_unused_variables(): void
    {
        $prompt = new Prompt('t', 1, 'sys', 'Title: {{title}}');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('missing: title; unused: titel');
        $prompt->render(['titel' => 'typo']);
    }

    public function test_placeholders_inside_values_are_not_expanded(): void
    {
        $prompt = new Prompt('t', 1, 'sys', '{{a}} / {{b}}');

        $this->assertSame('{{b}} / x', $prompt->render(['a' => '{{b}}', 'b' => 'x']));
    }
}
