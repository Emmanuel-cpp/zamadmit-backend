<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Services\DocumentVerificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Documents are verified server-side using Azure Computer Vision.
 *
 * Azure's Read API extracts text from images and PDFs with high accuracy,
 * and the Face Detection API counts faces in passport photos. This gives
 * us production-grade document verification with predictable performance.
 *
 * Each user has a single document of each type — uploading a new one
 * replaces the previous one (both the database row and the underlying file).
 */
class DocumentController extends Controller
{
    public function __construct(
        private DocumentVerificationService $verifier,
    ) {}

    /**
     * GET /api/documents
     */
    public function index(Request $request)
    {
        $documents = Document::where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->get();

        return response()->json($documents);
    }

    /**
     * POST /api/documents
     *
     * Synchronous verification — the request waits for Azure to respond.
     * If verification fails, the file is never persisted.
     *
     * If a document of the same type already exists for this user, it is
     * deleted (both DB row and physical file) before the new one is saved.
     * This enforces one document per type per user.
     */
    public function store(Request $request)
    {
        $request->validate([
            'file'     => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'type'     => 'required|in:nrc_front,nrc_back,certificate,photo',
            'user_nrc' => 'nullable|string',
        ]);

        $file = $request->file('file');
        $type = $request->type;

        // Run the appropriate verification on the uploaded file
        $verification = match ($type) {
            'nrc_front'   => $this->verifier->verifyNrc($file->getRealPath(), isFront: true,  userTypedNrc: $request->user_nrc),
            'nrc_back'    => $this->verifier->verifyNrc($file->getRealPath(), isFront: false),
            'certificate' => $this->verifier->verifyCertificate($file->getRealPath()),
            'photo'       => $this->verifier->verifyPassportPhoto($file->getRealPath()),
        };

        if (!$verification['ok']) {
            return response()->json([
                'message' => $verification['reason'],
                'status'  => 'rejected',
            ], 422);
        }

        // Verification passed.
        // Remove any existing document of the same type so we don't accumulate duplicates.
        $existing = Document::where('user_id', $request->user()->id)
            ->where('type', $type)
            ->get();

        foreach ($existing as $oldDoc) {
            if ($oldDoc->path) {
                Storage::disk('public')->delete($oldDoc->path);
            }
            $oldDoc->delete();
        }

        // Now store the new file and create its DB row
        $storedPath = $file->store("documents/{$request->user()->id}", 'public');

        $document = Document::create([
            'user_id'             => $request->user()->id,
            'name'                => $file->getClientOriginalName(),
            'type'                => $type,
            'path'                => $storedPath,
            'size_bytes'          => $file->getSize(),
            'verified'            => true,
            'verification_status' => 'verified',
            'verification_reason' => null,
            'ocr_text'            => $verification['ocr_text'] ?? null,
            'confidence_score'    => $verification['confidence'] ?? null,
        ]);

        return response()->json([
            'message'  => 'Document verified and uploaded successfully.',
            'status'   => 'verified',
            'document' => $document,
        ], 201);
    }

    /**
     * DELETE /api/documents/{id}
     */
    public function destroy(Request $request, int $id)
    {
        $document = Document::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        if ($document->path) {
            Storage::disk('public')->delete($document->path);
        }

        $document->delete();

        return response()->json(['message' => 'Document deleted.']);
    }
}