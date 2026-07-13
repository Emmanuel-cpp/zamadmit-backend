<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Document verification service powered by Azure Computer Vision.
 *
 * Uses two Azure APIs:
 *   - Read API for OCR (text extraction from documents)
 *   - Face Detection for counting faces in images
 *
 * Verification operates at multiple layers:
 *   - Format pattern matching (NRC number format)
 *   - OCR text marker detection (document-specific keywords)
 *   - Face detection (real NRC fronts have a photo)
 *
 * Each verify*() method takes a file path and returns:
 *   ['ok' => bool, 'reason' => ?string, 'ocr_text' => ?string, 'confidence' => float]
 */
class DocumentVerificationService
{
    private string $apiKey;
    private string $endpoint;

    public function __construct()
    {
        $this->apiKey   = config('services.azure_vision.key');
        $this->endpoint = rtrim(config('services.azure_vision.endpoint'), '/');
    }

    /**
     * Verify NRC document (front or back).
     *
     * Front side: must have NRC number pattern + NRC-specific markers + a face
     * Back side: must have multiple back-side text markers
     */
    public function verifyNrc(string $filePath, bool $isFront = true, ?string $userTypedNrc = null): array
    {
        $text = $this->extractText($filePath);

        if ($text === null) {
            return $this->reject('Could not read this file. Please upload a clear image or PDF.');
        }

        $upperText = strtoupper($text);

        Log::info('NRC verification', [
            'is_front' => $isFront,
            'text'     => $text,
        ]);

        if ($isFront) {
            // ── FRONT of NRC ──
            // Three-layer verification:
            //   1. NRC number pattern with slashes (123456/78/9)
            //   2. Multiple NRC-specific text markers
            //   3. Exactly one face detected (real NRCs have a photo of the holder)

            // Layer 1 — NRC number pattern
            $nrcMatched = preg_match('/(\d{6}\s*\/\s*\d{2}\s*\/\s*\d)/', $text, $matches);

            if (!$nrcMatched) {
                return $this->reject(
                    'Error verifying NRC front. Please upload a clear photo of the front of your NRC (the side with your registration number).',
                );
            }

            // Layer 2 — NRC-specific text markers (need at least 2)
            $nrcMarkers = [
                'REGISTRATION NUMBER',
                'REPUBLIC OF ZAMBIA',
                'SIGNATURE OF REGISTRATION OFFICER',
                'SIGNATURE OF HOLDER',
                'ONE ZAMBIA',
                'ONE NATION',
            ];

            $markerCount = 0;
            foreach ($nrcMarkers as $marker) {
                if (str_contains($upperText, $marker)) $markerCount++;
            }

            if ($markerCount < 2) {
                return $this->reject(
                    'Error verifying NRC front. This does not appear to be a Zambian NRC. Please upload a clear photo of the front of your NRC card.',
                );
            }

            // Layer 3 — must contain exactly 1 face (cardholder's photo)
            $faceCount = $this->countFaces($filePath);

            Log::info('NRC front face check', ['face_count' => $faceCount]);

            if ($faceCount === 0) {
                return $this->reject(
                    'Error verifying NRC front. No photo detected on the document. Please upload a clear image of your NRC front showing your photo.',
                );
            }

            if ($faceCount > 1) {
                return $this->reject(
                    'Error verifying NRC front. Multiple faces detected. Please upload a clear photo of just the front of your NRC.',
                );
            }

            // All 3 layers passed
            $detectedNrc = str_replace(' ', '', $matches[1]);

            if ($userTypedNrc) {
                $normalizedTyped = str_replace(' ', '', $userTypedNrc);
                if ($detectedNrc !== $normalizedTyped) {
                    return $this->reject(
                        'NRC number on the document does not match what you entered. Please check both and try again.',
                    );
                }
            }

            return [
                'ok'           => true,
                'reason'       => null,
                'ocr_text'     => $text,
                'confidence'   => 0.95,
                'detected_nrc' => $detectedNrc,
            ];
        }

        // ── BACK of NRC ──
        // Must have multiple back-specific markers
        $backMarkers = [
            'NATIONAL',
            'REGISTRATION CARD',
            'CARD NO',
            'FULL NAME',
            'DATE OF BIRTH',
            'PLACE OF BIRTH',
            'VILLAGE',
            'DISTRICT',
            'CHIEF',
            'REGISTRATION DATE',
        ];

        $matchCount = 0;
        foreach ($backMarkers as $marker) {
            if (str_contains($upperText, $marker)) $matchCount++;
        }

        if ($matchCount < 3) {
            return $this->reject(
                'Error verifying NRC back. Please upload a clear photo of the back of your NRC (the side with your name and date of birth).',
            );
        }

        return [
            'ok'         => true,
            'reason'     => null,
            'ocr_text'   => $text,
            'confidence' => min(1.0, $matchCount / count($backMarkers) + 0.3),
        ];
    }

    /**
     * Verify a Grade 12 / ECZ certificate.
     */
    public function verifyCertificate(string $filePath): array
    {
        $text = $this->extractText($filePath);

        if ($text === null) {
            return $this->reject('Could not read this file. Please upload a clear image or PDF.');
        }

        $upperText = strtoupper($text);

        $eczKeywords = [
            'EXAMINATIONS COUNCIL OF ZAMBIA',
            'ECZ',
            'GRADE 12',
            'SCHOOL CERTIFICATE',
            'STATEMENT OF RESULTS',
            'CERTIFICATE',
            'REPUBLIC OF ZAMBIA',
        ];

        $matchCount = 0;
        foreach ($eczKeywords as $kw) {
            if (str_contains($upperText, $kw)) $matchCount++;
        }

        if ($matchCount < 2) {
            return $this->reject(
                'Error verifying Grade 12 certificate. Please upload your official ECZ certificate or statement of results.',
            );
        }

        return [
            'ok'         => true,
            'reason'     => null,
            'ocr_text'   => $text,
            'confidence' => min(1.0, $matchCount / count($eczKeywords) + 0.3),
        ];
    }

    /**
     * Verify a passport-style photo.
     * Must contain exactly 1 face.
     */
    public function verifyPassportPhoto(string $filePath): array
    {
        $faceCount = $this->countFaces($filePath);

        if ($faceCount === 0) {
            return $this->reject(
                'Error verifying passport photo. Please upload a clear photo showing your face.',
            );
        }

        if ($faceCount > 1) {
            return $this->reject(
                'Error verifying passport photo. The image must contain only your face — no other people.',
            );
        }

        return [
            'ok'         => true,
            'reason'     => null,
            'ocr_text'   => null,
            'confidence' => 0.95,
        ];
    }

    /* ─────────────────────────────────
       Private helpers
    ───────────────────────────────── */

    /**
     * Extract text from a document using Azure Read API.
     * Read API is async — submit, then poll for the result.
     */
    private function extractText(string $filePath): ?string
    {
        try {
            $imageBytes = file_get_contents($filePath);

            // Step 1: Submit the image
            $readUrl = $this->endpoint . '/vision/v3.2/read/analyze';

            $submitResponse = Http::withHeaders([
                'Ocp-Apim-Subscription-Key' => $this->apiKey,
                'Content-Type'              => 'application/octet-stream',
            ])->withBody($imageBytes, 'application/octet-stream')
              ->timeout(30)
              ->post($readUrl);

            if (!$submitResponse->successful()) {
                Log::error('Azure Read API submit error', [
                    'status' => $submitResponse->status(),
                    'body'   => $submitResponse->body(),
                ]);
                return null;
            }

            // Step 2: Get the operation URL from the response headers
            $operationUrl = $submitResponse->header('Operation-Location');
            if (!$operationUrl) {
                Log::error('Azure Read API: no Operation-Location header');
                return null;
            }

            // Step 3: Poll until the operation completes
            $maxAttempts = 15;
            for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
                usleep(800_000); // 800ms between polls

                $resultResponse = Http::withHeaders([
                    'Ocp-Apim-Subscription-Key' => $this->apiKey,
                ])->timeout(10)->get($operationUrl);

                if (!$resultResponse->successful()) continue;

                $status = $resultResponse->json('status');

                if ($status === 'succeeded') {
                    $pages        = $resultResponse->json('analyzeResult.readResults', []);
                    $combinedText = '';
                    foreach ($pages as $page) {
                        foreach (($page['lines'] ?? []) as $line) {
                            $combinedText .= $line['text'] . "\n";
                        }
                    }
                    return $combinedText;
                }

                if ($status === 'failed') {
                    Log::error('Azure Read API operation failed', ['response' => $resultResponse->body()]);
                    return null;
                }
                // Otherwise keep polling
            }

            Log::error('Azure Read API timeout — operation did not complete');
            return null;

        } catch (\Throwable $e) {
            Log::error('Azure Read API exception', ['message' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Use Azure Face Detection to count faces in an image.
     * Returns the count, or 0 if the API call fails.
     */
    private function countFaces(string $filePath): int
    {
        try {
            $imageBytes = file_get_contents($filePath);
            $url        = $this->endpoint . '/vision/v3.2/analyze?visualFeatures=Faces';

            $response = Http::withHeaders([
                'Ocp-Apim-Subscription-Key' => $this->apiKey,
                'Content-Type'              => 'application/octet-stream',
            ])->withBody($imageBytes, 'application/octet-stream')
              ->timeout(30)
              ->post($url);

            if (!$response->successful()) {
                Log::error('Azure face count error', ['body' => $response->body()]);
                return 0;
            }

            return count($response->json('faces', []));

        } catch (\Throwable $e) {
            Log::error('Azure face count exception', ['message' => $e->getMessage()]);
            return 0;
        }
    }

    private function reject(string $reason): array
    {
        return [
            'ok'         => false,
            'reason'     => $reason,
            'ocr_text'   => null,
            'confidence' => 0.0,
        ];
    }
}