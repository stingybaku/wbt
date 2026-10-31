<?php

namespace App\Prompts;

/**
 * Versioned prompts used for LLM calls.
 *
 * Prompt text lives in resources/prompts/{key}/v{n}/system.md (and user.md
 * when the prompt has a user template). To change a prompt, add a new version
 * directory and bump CURRENT; old versions stay on disk so outputs can be
 * traced back to the exact text that produced them (the version id is
 * recorded on the module.generated event).
 *
 * module_generation@1 predates the registry: it was inlined in
 * GenerationService and asked for raw JSON text (see git history before
 * feature/phase0-foundations). v2 is the same instructions delivered through
 * the submit_module tool.
 */
class PromptRegistry
{
    public const MODULE_GENERATION = 'module_generation';
    public const SOURCE_CONDENSE = 'source_condense';

    /** @var array<string, int> key => version in use */
    public const CURRENT = [
        self::MODULE_GENERATION => 2,
        self::SOURCE_CONDENSE => 1,
    ];

    /** @var array<string, Prompt> */
    private array $loaded = [];

    public function __construct(private ?string $basePath = null)
    {
        $this->basePath ??= resource_path('prompts');
    }

    public function get(string $key, ?int $version = null): Prompt
    {
        $version ??= self::CURRENT[$key]
            ?? throw new \InvalidArgumentException("Unknown prompt: {$key}");

        return $this->loaded["{$key}@{$version}"] ??= $this->load($key, $version);
    }

    private function load(string $key, int $version): Prompt
    {
        $dir = "{$this->basePath}/{$key}/v{$version}";
        $systemPath = "{$dir}/system.md";

        if (! is_file($systemPath)) {
            throw new \InvalidArgumentException("Prompt {$key}@{$version} not found at {$dir}");
        }

        $userPath = "{$dir}/user.md";

        return new Prompt(
            key: $key,
            version: $version,
            system: rtrim(file_get_contents($systemPath)),
            userTemplate: is_file($userPath) ? rtrim(file_get_contents($userPath)) : null,
        );
    }
}
