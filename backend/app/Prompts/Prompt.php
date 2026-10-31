<?php

namespace App\Prompts;

/**
 * One version of one prompt, loaded from resources/prompts/{key}/v{version}/.
 *
 * The user template uses {{placeholder}} variables. render() refuses to leave
 * a placeholder unfilled or to accept a variable the template doesn't use, so
 * a renamed variable fails loudly instead of sending the model a literal
 * "{{source_text}}".
 */
final class Prompt
{
    public function __construct(
        public readonly string $key,
        public readonly int $version,
        public readonly string $system,
        public readonly ?string $userTemplate,
    ) {}

    /** Stable identifier for logs and events, e.g. "module_generation@2". */
    public function id(): string
    {
        return "{$this->key}@{$this->version}";
    }

    /** @param array<string, string|int> $vars */
    public function render(array $vars): string
    {
        if ($this->userTemplate === null) {
            throw new \LogicException("Prompt {$this->id()} has no user template.");
        }

        preg_match_all('/\{\{\s*([a-z_]+)\s*\}\}/', $this->userTemplate, $m);
        $expected = array_unique($m[1]);

        $missing = array_diff($expected, array_keys($vars));
        $unused = array_diff(array_keys($vars), $expected);
        if ($missing || $unused) {
            throw new \InvalidArgumentException(sprintf(
                'Prompt %s variables mismatch (missing: %s; unused: %s)',
                $this->id(),
                implode(', ', $missing) ?: 'none',
                implode(', ', $unused) ?: 'none',
            ));
        }

        // Substitute in a single pass so a value containing "{{x}}" (source
        // text can contain anything) is never itself expanded.
        return preg_replace_callback(
            '/\{\{\s*([a-z_]+)\s*\}\}/',
            fn (array $match) => (string) $vars[$match[1]],
            $this->userTemplate,
        );
    }
}
