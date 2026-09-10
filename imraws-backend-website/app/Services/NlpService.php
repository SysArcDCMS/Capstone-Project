<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Wrapper around the FastAPI NLP microservice (ai-nlp/).
 *
 * The microservice uses an async job queue: POST /process-complaint
 * returns a job_id, and GET /result/{job_id} returns the analysis.
 * This wrapper handles both calls and polls until completion.
 *
 * All 4 capstone categories are returned as-is:
 *   Billing, Water Quality, Metering, Operations
 *
 * Severity is High | Medium | Low (capstone DFD 2.10).
 * composite_score is the numeric DFD 2.9 output, persisted to
 * tbl_incidents so the panel can verify the calculation.
 */
class NlpService
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly int    $timeoutSeconds,
    ) {}

    /**
     * Run the full NLP pipeline on a complaint.
     *
     * @return array{
     *   category: string,
     *   category_confidence: float,
     *   sentiment: string,
     *   sentiment_score: float,
     *   composite_score: float,
     *   severity: string,
     * }
     *
     * @throws \RuntimeException when the service is unreachable or
     *                           the job fails.
     */
    public function processComplaint(string $text, int $daysPending = 0): array
    {
        // Step 1: Submit job
        $jobResponse = Http::timeout($this->timeoutSeconds)
            ->acceptJson()
            ->asJson()
            ->post(rtrim($this->baseUrl, '/').'/api/v1/process-complaint', [
                'complaint_text' => $text,
                'days_pending'   => $daysPending,
            ]);

        if ($jobResponse->failed()) {
            Log::error('NLP /process-complaint failed', [
                'status' => $jobResponse->status(),
                'body'   => $jobResponse->body(),
            ]);
            throw new \RuntimeException(
                "NLP service /process-complaint failed (HTTP {$jobResponse->status()})"
            );
        }

        $jobId = $jobResponse->json('job_id');
        if (! $jobId) {
            throw new \RuntimeException('NLP service did not return a job_id.');
        }

        // Step 2: Poll for result
        $deadline = microtime(true) + $this->timeoutSeconds;
        $pollIntervalMs = 250;

        while (microtime(true) < $deadline) {
            $resultResponse = Http::timeout(5)
                ->acceptJson()
                ->get(rtrim($this->baseUrl, '/')."/api/v1/result/{$jobId}");

            if ($resultResponse->status() === 404) {
                // Job not yet processed — wait and retry
                usleep($pollIntervalMs * 1000);
                $pollIntervalMs = min($pollIntervalMs * 2, 2000);
                continue;
            }

            if ($resultResponse->failed()) {
                throw new \RuntimeException(
                    "NLP service /result/{$jobId} failed (HTTP {$resultResponse->status()})"
                );
            }

            $payload = $resultResponse->json();

            if (($payload['status'] ?? null) === 'failed') {
                throw new \RuntimeException(
                    'NLP job failed: '.($payload['error'] ?? 'unknown error')
                );
            }

            if (($payload['status'] ?? null) === 'completed') {
                return [
                    'category'             => $payload['category']             ?? 'Operations',
                    'category_confidence'  => (float) ($payload['category_confidence']  ?? 0.0),
                    'sentiment'            => $payload['sentiment']            ?? 'NEUTRAL',
                    'sentiment_score'      => (float) ($payload['sentiment_score']      ?? 0.0),
                    'composite_score'      => (float) ($payload['composite_score']      ?? 0.0),
                    'severity'             => $payload['severity']             ?? 'Low',
                ];
            }

            usleep($pollIntervalMs * 1000);
        }

        throw new \RuntimeException("NLP service timed out waiting for job {$jobId}");
    }

    /**
     * Forward an Engineer HITL correction to the NLP service for logging.
     * Returns the FastAPI response array.
     */
    public function submitFeedback(array $feedback): array
    {
        $response = Http::timeout($this->timeoutSeconds)
            ->acceptJson()
            ->asJson()
            ->post(rtrim($this->baseUrl, '/').'/api/v1/submit-feedback', $feedback);

        if ($response->failed()) {
            throw new \RuntimeException(
                "NLP service /submit-feedback failed (HTTP {$response->status()})"
            );
        }

        return $response->json() ?? [];
    }
}
