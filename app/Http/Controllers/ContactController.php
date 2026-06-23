<?php

namespace App\Http\Controllers;

use App\Mail\ContactFormMail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class ContactController extends Controller
{
    public function send(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => ['required', 'string', 'min:2', 'max:120'],
                'email' => ['required', 'email', 'max:180'],
                'phone' => ['nullable', 'string', 'max:40'],
                'subject' => ['required', 'string', 'min:3', 'max:180'],
                'message' => ['required', 'string', 'min:10', 'max:3000'],
            ]);

            $to = env('CONTACT_MAIL_TO', env('MAIL_FROM_ADDRESS'));

            Mail::to($to)->send(new ContactFormMail($validated));

            return response()->json([
                'success' => true,
                'message' => 'Votre message a bien été envoyé. Nous vous répondrons rapidement.',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Certains champs sont invalides.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Erreur envoi formulaire contact Gotfit', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Impossible d’envoyer le message pour le moment. Veuillez réessayer plus tard.',
            ], 500);
        }
    }
}
