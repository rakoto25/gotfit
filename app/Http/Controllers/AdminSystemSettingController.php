<?php

namespace App\Http\Controllers;

use App\Services\SystemSettingRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AdminSystemSettingController extends Controller
{
    public function index(SystemSettingRegistry $registry): JsonResponse
    {
        return response()->json(['settings' => $registry->adminPayload()]);
    }

    public function update(Request $request, SystemSettingRegistry $registry): JsonResponse
    {
        $validated = $request->validate([
            'settings' => ['required', 'array', 'min:1'],
            'settings.*' => ['nullable'],
        ]);

        foreach ($validated['settings'] as $key => $value) {
            abort_unless($registry->isAllowed((string) $key), 422, "Le paramètre {$key} n’est pas autorisé.");

            $definition = $registry->definitions()[$key];
            if ($definition['secret'] && blank($value)) {
                continue;
            }

            $registry->put((string) $key, $value, (int) $request->user()->id);
        }

        Log::notice('Configuration système mise à jour.', [
            'admin_id' => $request->user()->id,
            'keys' => array_keys($validated['settings']),
        ]);

        return response()->json([
            'message' => 'Configuration enregistrée.',
            'settings' => $registry->adminPayload(),
        ]);
    }

    public function destroy(Request $request, string $key, SystemSettingRegistry $registry): JsonResponse
    {
        $registry->forget($key);

        Log::notice('Configuration système réinitialisée.', [
            'admin_id' => $request->user()->id,
            'key' => $key,
        ]);

        return response()->json([
            'message' => 'Le paramètre utilise de nouveau la valeur d’environnement.',
        ]);
    }
}
