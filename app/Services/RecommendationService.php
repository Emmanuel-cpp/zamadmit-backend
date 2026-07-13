<?php

namespace App\Services;

use App\Models\Programme;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Calls Azure OpenAI to generate personalized programme recommendations
 * for a student, with natural-language reasoning per recommendation.
 *
 * Architecture:
 *   1. We send the student's profile (grades, province) plus a slimmed-down
 *      catalog of currently-open programmes to GPT.
 *   2. The model returns a ranked list with reasoning specific to the
 *      student's strengths.
 *   3. We hydrate the model's slugs back into full programme records
 *      for the response, so the frontend receives complete data.
 *
 * Why an LLM and not a pure scoring algorithm? Because reasoning like
 *   "Your strong Math grade exceeds the requirement, and Mining
 *    Engineering matches the technical bent of your physics performance"
 * is hard to write with rules but trivial for an LLM.
 */
class RecommendationService
{
    public function recommend(User $student): array
    {
        $endpoint   = config('services.azure_openai.endpoint');
        $key        = config('services.azure_openai.key');
        $deployment = config('services.azure_openai.deployment');
        $apiVersion = config('services.azure_openai.api_version');

        if (!$endpoint || !$key || !$deployment) {
            throw new \RuntimeException('Azure OpenAI is not configured. Check AZURE_OPENAI_* env vars.');
        }

        // 1. Gather what we need about the student
        $studentSummary = $this->buildStudentSummary($student);

        // 2. Gather available programmes (currently accepting applications)
        $programmes = $this->buildProgrammeCatalog();

        if (empty($programmes)) {
            return [
                'recommendations' => [],
                'reasoning_note'  => 'No programmes are currently open for application.',
            ];
        }

        // 3. Build the prompt
        $systemPrompt = $this->systemPrompt();
        $userPrompt   = $this->userPrompt($studentSummary, $programmes);

        // 4. Call Azure OpenAI
        $url = "{$endpoint}/openai/deployments/{$deployment}/chat/completions?api-version={$apiVersion}";

        $response = Http::withHeaders([
            'api-key'      => $key,
            'Content-Type' => 'application/json',
        ])
            ->timeout(45)
            ->post($url, [
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user',   'content' => $userPrompt],
                ],
                'response_format' => ['type' => 'json_object'],
                'temperature'     => 0.4,  // some creativity, but stay grounded
                'max_completion_tokens' => 1500,
            ]);

        if (!$response->successful()) {
            Log::error('Azure OpenAI call failed', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            throw new \RuntimeException('Recommendation service is temporarily unavailable.');
        }

        // 5. Parse the model's response
        $body    = $response->json();
        $content = $body['choices'][0]['message']['content'] ?? '';

        $parsed = json_decode($content, true);
        if (!is_array($parsed) || !isset($parsed['recommendations'])) {
            Log::warning('Azure OpenAI returned unexpected payload', ['content' => $content]);
            throw new \RuntimeException('Could not parse recommendation response.');
        }

        // 6. Hydrate slugs back into full programme records
        return $this->hydrateRecommendations($parsed);
    }

    /**
     * Produce a plain-text summary of the student for the LLM prompt.
     */
        private function buildStudentSummary(User $student): string
        {
            $student->load('grades');

            $name = $student->first_name . ' ' . $student->last_name;

            $grades = $student->grades->isEmpty()
                ? 'No grades recorded'
                : $student->grades
                    ->map(fn ($g) => "{$g->subject}: {$g->grade}")
                    ->join(', ');

            $interests = is_array($student->interests) && count($student->interests) > 0
                ? implode(', ', $student->interests)
                : 'None declared';

            return "Name: {$name}\nECZ Grade 12 results: {$grades}\nDeclared interests: {$interests}";
        }

    /**
     * Build a slim programme catalog with everything the LLM needs to reason
     * but nothing extra (saves tokens).
     */
    private function buildProgrammeCatalog(): array
    {
        $programmes = Programme::with(['institution', 'requirements'])
            ->whereHas('institution', function ($q) {
                $q->where('is_accepting_applications', true);
            })
            ->get();

        return $programmes->map(function ($p) {
            $requirements = $p->requirements
                ->map(fn ($r) => "{$r->subject} min {$r->min_grade}")
                ->join(', ');

            return [
                'slug'         => $p->slug,
                'name'         => $p->name,
                'qualification'=> $p->qualification,
                'school'       => $p->school,
                'institution'  => $p->institution?->short_name,
                'requirements' => $requirements ?: 'No specific requirements',
            ];
        })->all();
    }

        private function systemPrompt(): string
        {
            return <<<PROMPT
        You are ZamAdmit's AI advisor for Zambian university applicants.
        You help students discover programmes that match their academic strengths
        and declared interests based on their ECZ Grade 12 results.

        When evaluating fit, consider in this order:
        1. Whether the student meets each programme's minimum requirements
        2. How strongly their grades exceed those minimums (especially in subjects
        relevant to the programme)
        3. Alignment with the student's declared interests — these signal what
        the student actually wants to study, which matters more than raw grades
        4. Variety: don't only recommend programmes from one school or category

        ECZ grading scale: 1 is best, 9 is worst. Grade 1-3 is "distinction",
        4-6 is "credit", 7-9 is "pass" or fail. So a Math grade of 2 means the
        student is very strong in Math.

        Your response MUST be valid JSON in this exact shape:
        {
        "recommendations": [
            {
            "slug": "<programme-slug>",
            "match_score": <integer 0-100>,
            "reasons": [
                "<short reason 1>",
                "<short reason 2>",
                "<short reason 3>"
            ]
            }
        ],
        "reasoning_note": "<one sentence summarizing your overall approach for this student>"
        }

        Return between 3 and 6 recommendations. Higher match_score = better fit.
        Each reason should be 1-2 sentences, personalized to THIS student. When
        relevant, explicitly tie a reason back to their declared interests.
        PROMPT;
        }

    private function userPrompt(string $studentSummary, array $programmes): string
    {
        $programmeJson = json_encode($programmes, JSON_PRETTY_PRINT);

        return <<<PROMPT
    Here is the student profile:

    {$studentSummary}

    Here is the catalog of programmes currently accepting applications:

    {$programmeJson}

    Return your top recommendations as JSON, following the schema in your instructions.
    Only include programmes from the catalog above. Use the exact `slug` values.
    PROMPT;
        }

        /**
         * Convert the LLM's slug-based response into full programme records
         * the frontend can render directly.
         */
        private function hydrateRecommendations(array $parsed): array
        {
            $slugs = collect($parsed['recommendations'])->pluck('slug')->all();

            $programmes = Programme::with(['institution'])
                ->whereIn('slug', $slugs)
                ->get()
                ->keyBy('slug');

        $hydrated = collect($parsed['recommendations'])
            ->filter(fn ($rec) => $programmes->has($rec['slug']))
            ->map(function ($rec) use ($programmes) {
                $programme = $programmes->get($rec['slug']);
                return [
                    'match_score' => $rec['match_score'] ?? 0,
                    'reasons'     => $rec['reasons'] ?? [],
                    'programme'   => [
                        'id'            => $programme->id,
                        'slug'          => $programme->slug,
                        'name'          => $programme->name,
                        'qualification' => $programme->qualification,
                        'school'        => $programme->school,
                        'duration_years'=> $programme->duration_years,
                        'study_mode'    => $programme->study_mode,
                    ],
                    'institution' => [
                        'id'         => $programme->institution?->id,
                        'slug'       => $programme->institution?->slug,
                        'name'       => $programme->institution?->name,
                        'short_name' => $programme->institution?->short_name,
                        'city'       => $programme->institution?->city,
                        'province'   => $programme->institution?->province,
                    ],
                ];
            })
            ->values()
            ->all();

        return [
            'recommendations' => $hydrated,
            'reasoning_note'  => $parsed['reasoning_note'] ?? null,
        ];
    }
}