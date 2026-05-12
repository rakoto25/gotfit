<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{

    // Liste
    public function index()
    {
        $document = Document::latest()->get();

        return response()->json([
            'status' => 200,
            'documents' => $document,
        ]);
    }

    // Upload document
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'file' => 'required|file|mimes:pdf,doc,docx,png,jpg|max:2048',
        ]);

        $filePath = $request->file('file')->store('documents', 'public');

        $document = Document::create([
            'name' => $request->name,
            'file_path' => $filePath,
            'status' => 'brouillon', // par défaut
        ]);

        return response()->json([
            'status' => 200,
            'message' => 'Document uploadé avec succès',
            'data' => $document
        ]);
    }

     // Changer le statut
     public function validerDocument($id)
    {
        $document = Document::findOrFail($id);

        $document->update([
            'status' => 'valide'
        ]);

        return response()->json([
            'status' => 200,
            'message' => 'Document validé avec succès',
            'documents' => $document
        ]);
    }

    public function refuserDocument($id)
    {
        $document = Document::findOrFail($id);

        $document->update([
            'status' => 'refuse'
        ]);

        return response()->json([
            'status' => 200,
            'message' => 'Document refusé avec succès',
            'documents' => $document
        ]);
    }

     public function destroy($id)
    {
        $document = Document::findOrFail($id);

        // Supprimer le fichier du storage
        if ($document->file_path && Storage::disk('public')->exists($document->file_path)) {
            Storage::disk('public')->delete($document->file_path);
        }

        // Supprimer l'enregistrement en base
        $document->delete();

        return response()->json([
            'status' => 200,
            'message' => 'Document supprimé avec succès'
        ]);
    }
 
}
