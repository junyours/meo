<?php

namespace App\Http\Controllers;

use App\Models\DocumentScanner;
use App\Models\Projects;
use App\Models\Techprep;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DocumentController extends Controller
{
    public function upload(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|max:51200', // Max 50MB
            'document_name' => 'required|string|max:255',
            'project_id' => 'nullable|exists:project_tb,id',
            'techprep_id' => 'nullable|exists:tech_prep_tb,id',
            'document_type' => 'nullable|string|in:pdf,jpg,jpeg,png,docx,doc,txt',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        try {
            $file = $request->file('file');
            $documentType = $request->document_type ?? $file->getClientOriginalExtension();
            
            // Store file
            $fileName = Str::uuid() . '.' . $file->getClientOriginalExtension();
            $filePath = $file->storeAs('documents', $fileName, 'local');
            
            // Calculate file hash
            $fileHash = hash_file('sha256', $file->getPathname());
            
            // Get file size
            $fileSize = $file->getSize();
            
            // Create document record
            $document = DocumentScanner::create([
                'document_name' => $request->document_name,
                'document_type' => $documentType,
                'file_path' => $filePath,
                'file_hash' => $fileHash,
                'file_size' => $fileSize,
                'page_number' => $request->integer('page_number') ?: null,
                'project_id' => $request->project_id,
                'techprep_id' => $request->techprep_id,
                'uploaded_by' => auth()->id(),
                'processing_status' => 'pending',
                'scan_device' => $request->header('User-Agent'),
                'scan_ip' => $request->ip(),
            ]);

            // Queue OCR processing (if PDF or image)
            if (in_array($documentType, ['pdf', 'jpg', 'jpeg', 'png'])) {
                $this->processOCR($document);
            }

            return response()->json([
                'message' => 'Document uploaded successfully',
                'document' => $document->load('uploader'),
            ], 201);
        } catch (\Exception $e) {
            Log::error('Document upload failed', [
                'user_id' => auth()->id(),
                'project_id' => $request->input('project_id'),
                'techprep_id' => $request->input('techprep_id'),
                'file_name' => $request->file('file')?->getClientOriginalName(),
                'exception' => $e,
            ]);
            return response()->json(['message' => 'Upload failed: ' . $e->getMessage()], 500);
        }
    }

    public function index(Request $request): JsonResponse
    {
        // Allow access for authenticated users with appropriate roles
        $user = auth()->user();
        if (!$user || !in_array($user->role, ['superadmin', 'admin', 'staff'])) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        $query = DocumentScanner::with(['project', 'techprep', 'uploader']);

        if ($request->has('project_id')) {
            $query->byProject($request->project_id);
        }

        if ($request->has('techprep_id')) {
            $query->byTechprep($request->techprep_id);
        }

        if ($request->has('document_type')) {
            $query->byType($request->document_type);
        }

        if ($request->has('processing_status')) {
            $query->where('processing_status', $request->processing_status);
        }

        $documents = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json($documents);
    }

    public function show(DocumentScanner $document): JsonResponse
    {
        $user = auth()->user();
        // Check access permissions
        if (!$document->is_public && !in_array($user?->role, ['admin', 'superadmin', 'staff'], true) && !$document->isAccessibleBy($user?->id)) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        return response()->json($document->load(['project', 'techprep', 'uploader', 'parentDocument', 'versions']));
    }

    public function preview(DocumentScanner $document)
    {
        $user = auth()->user();
        $canPreview = $document->is_public
            || $document->uploaded_by === $user?->id
            || in_array($user?->role, ['admin', 'superadmin', 'staff'], true)
            || $document->isAccessibleBy($user?->id);

        if (!$canPreview) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        $path = Storage::disk('local')->path($document->file_path);
        if (!is_file($path)) {
            return response()->json(['message' => 'File not found'], 404);
        }

        return response()->file($path, [
            'Content-Disposition' => 'inline; filename="' . addslashes($document->document_name) . '"',
        ]);
    }

    public function download(DocumentScanner $document)
    {
        $user = auth()->user();
        $canDownload = $document->is_public
            || $document->uploaded_by === $user?->id
            || in_array($user?->role, ['admin', 'superadmin', 'staff'], true)
            || $document->isAccessibleBy($user?->id);

        if (!$canDownload) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        if (!Storage::exists($document->file_path)) {
            return response()->json(['message' => 'File not found'], 404);
        }

        return Storage::download($document->file_path, $document->document_name);
    }

    public function update(Request $request, DocumentScanner $document): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'document_name' => 'nullable|string|max:255',
            'is_public' => 'nullable|boolean',
            'access_permissions' => 'nullable|array',
            'expires_at' => 'nullable|date',
            'page_number' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        try {
            $document->update($request->only([
                'document_name',
                'is_public',
                'access_permissions',
                'expires_at',
                'page_number',
            ]));

            return response()->json([
                'message' => 'Document updated successfully',
                'document' => $document,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Update failed: ' . $e->getMessage()], 500);
        }
    }

    public function destroy(DocumentScanner $document): JsonResponse
    {
        // Check if user has permission
        if ($document->uploaded_by !== auth()->id() && auth()->user()?->role !== 'superadmin') {
            return response()->json(['message' => 'Access denied'], 403);
        }

        try {
            // Delete file from storage
            if (Storage::exists($document->file_path)) {
                Storage::delete($document->file_path);
            }

            // Soft delete the record
            $document->delete();

            return response()->json(['message' => 'Document deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Delete failed: ' . $e->getMessage()], 500);
        }
    }

    public function createVersion(Request $request, DocumentScanner $document): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|max:51200',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        try {
            $file = $request->file('file');
            
            // Store new file
            $fileName = Str::uuid() . '.' . $file->getClientOriginalExtension();
            $filePath = $file->storeAs('documents', $fileName, 'local');
            
            // Calculate file hash
            $fileHash = hash_file('sha256', $file->getPathname());
            
            // Create new version
            $newVersion = $document->createNewVersion();
            $newVersion->update([
                'file_path' => $filePath,
                'file_hash' => $fileHash,
                'file_size' => $file->getSize(),
                'document_type' => $file->getClientOriginalExtension(),
            ]);

            // Queue OCR processing
            if (in_array($newVersion->document_type, ['pdf', 'jpg', 'jpeg', 'png'])) {
                $this->processOCR($newVersion);
            }

            return response()->json([
                'message' => 'New version created successfully',
                'document' => $newVersion,
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Version creation failed: ' . $e->getMessage()], 500);
        }
    }

    public function verifyIntegrity(DocumentScanner $document): JsonResponse
    {
        $isValid = $document->verifyIntegrity();

        return response()->json([
            'valid' => $isValid,
            'stored_hash' => $document->file_hash,
            'calculated_hash' => $document->calculateFileHash(),
        ]);
    }

    public function grantAccess(Request $request, DocumentScanner $document): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $document->grantAccess($request->user_id);

        return response()->json(['message' => 'Access granted successfully']);
    }

    public function revokeAccess(Request $request, DocumentScanner $document): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $document->revokeAccess($request->user_id);

        return response()->json(['message' => 'Access revoked successfully']);
    }

    public function search(Request $request): JsonResponse
    {
        $query = $request->get('q');
        
        if (empty($query)) {
            return response()->json(['message' => 'Query parameter is required'], 422);
        }

        $documents = DocumentScanner::where('document_name', 'like', "%{$query}%")
            ->orWhere('extracted_text', 'like', "%{$query}%")
            ->orWhereJsonContains('ai_tags', $query)
            ->with(['project', 'techprep', 'uploader'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($documents);
    }

    private function processOCR(DocumentScanner $document): void
    {
        // Keep processing compatible with the current schema. OCR and AI
        // results have dedicated tables, while this table stores the status.
        $document->markAsProcessing();

        try {
            // OCR/AI integration can populate their dedicated tables later.
            $document->markAsCompleted();
        } catch (\Exception $e) {
            $document->markAsFailed($e->getMessage());
        }
    }

    private function processAI(DocumentScanner $document): void
    {
        // AI analysis is stored in document_ai_analysis_tb when enabled.
    }
}
