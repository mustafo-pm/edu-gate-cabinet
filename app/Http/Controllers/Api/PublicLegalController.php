<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LegalDocument;
use Illuminate\Http\JsonResponse;

/**
 * Legal documents for the marketing site and for PSP apps that have to show
 * the offer inside their own flow.
 *
 * Alongside the HTML pages rather than instead of them: this endpoint lets a
 * static site or a mobile app render the text in its own shell, while
 * /hujjat/{slug} stays a working, crawlable address that needs no JavaScript.
 * A legal document must be readable even when a script fails.
 *
 * Deliberately outside /api/v1 — that prefix is the PSP contract and carries
 * Sanctum auth. This is public by definition.
 */
class PublicLegalController extends Controller
{
    /** GET /api/public/legal — what exists, without the bodies. */
    public function index(): JsonResponse
    {
        $items = LegalDocument::active()
            ->orderBy('sort_order')
            ->get()
            ->map(function (LegalDocument $doc) {
                $current = $doc->currentVersion();

                return $current === null ? null : [
                    'slug' => $doc->slug,
                    'type' => $doc->type->value,
                    'version' => $current->version,
                    'effective_from' => $current->effective_from?->toDateString(),
                    'title' => [
                        'uz' => $current->title('uz'),
                        'ru' => $current->title('ru'),
                        'en' => $current->title('en'),
                    ],
                    'url' => url('/hujjat/'.$doc->slug),
                ];
            })
            ->filter()   // a document with no published version is not public yet
            ->values();

        return response()
            ->json(['status' => 'ok', 'data' => $items])
            ->header('Cache-Control', 'public, max-age=300');
    }

    /** GET /api/public/legal/{slug} — one document, all three languages. */
    public function show(string $slug): JsonResponse
    {
        $doc = LegalDocument::active()->where('slug', $slug)->first();
        $current = $doc?->currentVersion();

        if ($current === null) {
            return response()->json([
                'status' => 'error',
                'error' => ['code' => 'not_found', 'message' => 'Document not found'],
            ], 404);
        }

        $upcoming = $doc->upcomingVersion();

        return response()
            ->json(['status' => 'ok', 'data' => [
                'slug' => $doc->slug,
                'type' => $doc->type->value,
                'version' => $current->version,
                'published_at' => $current->published_at?->toIso8601String(),
                'effective_from' => $current->effective_from?->toDateString(),

                'title' => [
                    'uz' => $current->title('uz'),
                    'ru' => $current->title('ru'),
                    'en' => $current->title('en'),
                ],

                // Both forms: markdown for anyone who wants to restyle it,
                // rendered HTML for anyone who just wants to show it.
                'body_markdown' => [
                    'uz' => $current->body('uz'),
                    'ru' => $current->body('ru'),
                    'en' => $current->body('en'),
                ],
                'body_html' => [
                    'uz' => $current->html('uz'),
                    'ru' => $current->html('ru'),
                    'en' => $current->html('en'),
                ],

                // Announced but not yet binding. A caller showing the offer
                // inside a payment flow can warn about it.
                'upcoming' => $upcoming === null ? null : [
                    'version' => $upcoming->version,
                    'effective_from' => $upcoming->effective_from?->toDateString(),
                ],

                'url' => url('/hujjat/'.$doc->slug),
            ]])
            ->header('Cache-Control', 'public, max-age=300');
    }
}
