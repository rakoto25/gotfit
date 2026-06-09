<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    public function index()
    {
        $documents = Document::with(['user:id,name,email', 'validator:id,name'])->latest()->get();

        return response()->json(['status' => 200, 'documents' => $documents]);
    }

    public function myDocuments()
    {
        $documents = Document::where('user_id', Auth::id())->latest()->get();
        return response()->json(['status' => 200, 'documents' => $documents]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'document_type' => 'nullable|string|max:100',
            'file' => 'required|file|mimes:pdf,doc,docx,png,jpg,jpeg|max:4096',
        ]);

        $filePath = $request->file('file')->store('documents', 'public');

        $document = Document::create([
            'user_id' => Auth::id(),
            'name' => $request->name,
            'document_type' => $request->document_type,
            'file_path' => $filePath,
            'status' => 'en_attente',
        ]);

        return response()->json([
            'status' => 200,
            'message' => 'Document uploadé avec succès. Il attend la validation admin.',
            'data' => $document,
        ]);
    }

    public function validerDocument($id)
    {
        $document = Document::findOrFail($id);
        $document->update([
            'status' => 'valide',
            'validated_by' => Auth::id(),
            'validated_at' => now(),
            'rejection_reason' => null,
        ]);

        return response()->json(['status' => 200, 'message' => 'Document validé avec succès', 'document' => $document]);
    }

    public function refuserDocument(Request $request, $id)
    {
        $request->validate(['rejection_reason' => 'nullable|string']);
        $document = Document::findOrFail($id);
        $document->update([
            'status' => 'refuse',
            'validated_by' => Auth::id(),
            'validated_at' => now(),
            'rejection_reason' => $request->rejection_reason,
        ]);

        return response()->json(['status' => 200, 'message' => 'Document refusé avec succès', 'document' => $document]);
    }

    public function destroy($id)
    {
        $document = Document::findOrFail($id);
        $user = Auth::user();

        if (!$user->hasRole('admin') && (int) $document->user_id !== (int) $user->id) {
            return response()->json(['status' => 403, 'message' => 'Non autorisé'], 403);
        }

        if ($document->file_path && Storage::disk('public')->exists($document->file_path)) {
            Storage::disk('public')->delete($document->file_path);
        }

        $document->delete();

        return response()->json(['status' => 200, 'message' => 'Document supprimé avec succès']);
    }
}
