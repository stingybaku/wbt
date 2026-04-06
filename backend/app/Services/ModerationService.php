<?php

namespace App\Services;

use Anthropic\Client;
use Illuminate\Support\Facades\Log;

class ModerationService
{
    private Client $client;
    private string $fastModel;

    public function __construct()
    {
        $this->client    = new Client(apiKey: config('services.anthropic.api_key'));
        $this->fastModel = config('services.anthropic.model_fast', 'claude-haiku-4-5-20251001');
    }

    /**
     * Check whether the given text is safe to use as course source material.
     *
     * Returns an object with:
     *   - safe: bool
     *   - reason: string|null  (populated only when safe === false)
     */
    public function check(string $text): object
    {
        // Only send the first 6000 chars — enough context, cheap to run
        $excerpt = mb_substr($text, 0, 6000);

        $prompt = <<<PROMPT
You are a content moderation classifier for an e-learning platform. Evaluate the following source text and decide whether it is safe to use as educational course material.

CLASSIFICATION RULES:
- Mark SAFE if the text discusses sensitive, difficult, or disturbing topics from a protective, awareness, preventive, educational, or historical perspective — even if the subject matter is uncomfortable (e.g., abuse awareness, violence prevention, drug harm reduction, exploitation safeguarding, war history).
- Mark UNSAFE only if the text directly instructs readers how to commit harmful acts, recruits them to commit harmful acts, glorifies or normalises violence/exploitation/terrorism, or is clearly designed to cause real-world harm.
- Do NOT flag academic analysis, journalistic reporting, clinical/medical content, safeguarding training, or discussions of illegal activities from an informational or preventive angle.

Respond with a single valid JSON object and nothing else:
{ "safe": true }
OR
{ "safe": false, "reason": "one-sentence explanation of what makes this content unsafe" }

SOURCE TEXT:
{$excerpt}
PROMPT;

        try {
            $response = $this->client->messages->create(
                maxTokens: 256,
                messages: [['role' => 'user', 'content' => $prompt]],
                model: $this->fastModel,
            );

            $raw  = trim($response->content[0]->text ?? '');
            $data = json_decode($raw);

            if (json_last_error() !== JSON_ERROR_NONE || !isset($data->safe)) {
                Log::warning('ModerationService: unexpected response — defaulting to safe', ['raw' => $raw]);
                return (object) ['safe' => true, 'reason' => null];
            }

            return $data;
        } catch (\Throwable $e) {
            // On moderation failure, fail open (allow) so a transient API error doesn't block legitimate users
            Log::error('ModerationService: check failed — defaulting to safe', ['error' => $e->getMessage()]);
            return (object) ['safe' => true, 'reason' => null];
        }
    }
}
