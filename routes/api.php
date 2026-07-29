<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminMessageController;
use App\Http\Controllers\AnnonceController;
use App\Http\Controllers\ClientJourneyController;
use App\Http\Controllers\ConnexionController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\FitnessAssessmentController;
use App\Http\Controllers\InscriptionController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\MissionController;
use App\Http\Controllers\PayementController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PushTokenController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SocialAuthController;
use App\Http\Controllers\VisioSessionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\PersonalAccessToken;

/*
|--------------------------------------------------------------------------
| DEBUG TEMPORAIRE AUTHORIZATION HEADER
|--------------------------------------------------------------------------
| Sécurisé : ces routes ne sont chargées que si APP_DEBUG=true.
|--------------------------------------------------------------------------
*/

if (config('app.debug')) {
    Route::get('/debug-auth-header', function (Request $request) {
        return response()->json([
            'authorization_header' => $request->header('Authorization'),
            'bearer_token' => $request->bearerToken(),
            'server_http_authorization' => $_SERVER['HTTP_AUTHORIZATION'] ?? null,
            'server_authorization' => $_SERVER['Authorization'] ?? null,
            'all_headers' => $request->headers->all(),
        ]);
    });

    Route::get('/debug-sanctum-user', function (Request $request) {
        $plainToken = $request->bearerToken();

        $accessToken = $plainToken
            ? PersonalAccessToken::findToken($plainToken)
            : null;

        $tokenable = $accessToken ? $accessToken->tokenable : null;

        return response()->json([
            'bearer_token' => $plainToken,
            'access_token_found' => $accessToken ? true : false,
            'access_token_id' => $accessToken?->id,
            'token_name' => $accessToken?->name,
            'token_created_at' => $accessToken?->created_at,
            'token_last_used_at' => $accessToken?->last_used_at,
            'token_expires_at' => $accessToken?->expires_at,
            'sanctum_config_expiration' => config('sanctum.expiration'),
            'tokenable_type' => $accessToken?->tokenable_type,
            'tokenable_id' => $accessToken?->tokenable_id,
            'tokenable_found' => $tokenable ? true : false,
            'tokenable_user' => $tokenable,
            'auth_sanctum_check' => auth('sanctum')->check(),
            'auth_sanctum_user' => auth('sanctum')->user(),
            'auth_default_check' => auth()->check(),
            'auth_default_user' => auth()->user(),
        ]);
    });
}

/*
|--------------------------------------------------------------------------
| AUTHENTIFICATION PUBLIQUE
|--------------------------------------------------------------------------
*/

Route::post('/register', [InscriptionController::class, 'register'])
    ->name('auth.register');

Route::post('/login', [ConnexionController::class, 'login'])
    ->name('auth.login');

Route::post('/forgot-password', [ConnexionController::class, 'forgotPassword'])
    ->name('auth.forgot-password');

Route::post('/reset-password', [ConnexionController::class, 'resetPassword'])
    ->name('auth.reset-password');

/*
|--------------------------------------------------------------------------
| AUTHENTIFICATION GOOGLE
|--------------------------------------------------------------------------
| Le navigateur transmet le jeton Google Identity Services. Laravel vérifie
| l'identité, crée le compte au premier passage et renvoie un token Sanctum.
|--------------------------------------------------------------------------
*/

Route::post('/auth/google', [SocialAuthController::class, 'google'])
    ->middleware('throttle:5,1')
    ->name('auth.google');

/*
|--------------------------------------------------------------------------
| ROUTES PUBLIQUES
|--------------------------------------------------------------------------
*/

Route::get('/annonces', [AnnonceController::class, 'index']);
Route::get('/annonces/{id}/detail', [AnnonceController::class, 'detailAnnonce'])->whereNumber('id');

/*
|--------------------------------------------------------------------------
| INTERVENANTS PUBLICS
|--------------------------------------------------------------------------
*/

Route::get('/intervenants', [ProfileController::class, 'publicIntervenants']);
Route::get('/intervenants/{id}/reviews', [ReviewController::class, 'byIntervenant'])->whereNumber('id');

/*
|--------------------------------------------------------------------------
| MISSIONS PUBLIQUES
|--------------------------------------------------------------------------
*/

Route::get('/missions', [MissionController::class, 'index']);

/*
|--------------------------------------------------------------------------
| VISIO PUBLIQUE
|--------------------------------------------------------------------------
*/

Route::get('/visio/sessions', [VisioSessionController::class, 'index']);
Route::get('/visio/sessions/{id}', [VisioSessionController::class, 'show'])->middleware('auth:sanctum')->whereNumber('id');

/*
|--------------------------------------------------------------------------
| CONTACT PUBLIC
|--------------------------------------------------------------------------
*/

Route::post('/contact', [ContactController::class, 'send'])
    ->middleware('throttle:5,1');

/*
|--------------------------------------------------------------------------
| STRIPE WEBHOOK PUBLIC
|--------------------------------------------------------------------------
*/

Route::post('/payment/webhook', [PayementController::class, 'handleWebhook']);

/*
|--------------------------------------------------------------------------
| RETOURS PUBLICS STRIPE CONNECT
|--------------------------------------------------------------------------
*/

Route::get('/stripe/connect/return', [PayementController::class, 'connectReturn']);
Route::get('/stripe/connect/refresh', [PayementController::class, 'connectRefresh']);

/*
|--------------------------------------------------------------------------
| ROUTES CONNECTÉES COMMUNES
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [ConnexionController::class, 'logout']);
    Route::post('/push-tokens', [PushTokenController::class, 'store']);
    Route::delete('/push-tokens', [PushTokenController::class, 'destroy']);

    Route::get('/profile', [ProfileController::class, 'show']);
    Route::post('/profile', [ProfileController::class, 'update']);
    Route::post('/profile/update', [ProfileController::class, 'update']);
    Route::match(['put', 'patch'], '/profile', [ProfileController::class, 'update']);

    /*
    |--------------------------------------------------------------------------
    | STRIPE CONNECT INTERVENANT
    |--------------------------------------------------------------------------
    */

    Route::post('/stripe/connect/onboarding', [PayementController::class, 'createConnectOnboarding']);
    Route::get('/stripe/connect/status', [PayementController::class, 'connectStatus']);
    Route::get('/my-payments', [PayementController::class, 'myPayments']);
    Route::get('/payments/me', [PayementController::class, 'myPayments']);

    /*
    |--------------------------------------------------------------------------
    | FAVORIS
    |--------------------------------------------------------------------------
    */

    Route::get('/favorites', [FavoriteController::class, 'index']);
    Route::post('/favorites/{annonce}', [FavoriteController::class, 'store'])->whereNumber('annonce');
    Route::delete('/favorites/annonce/{annonce}', [FavoriteController::class, 'destroyByAnnonce'])->whereNumber('annonce');
    Route::delete('/favorites/{id}', [FavoriteController::class, 'destroy'])->whereNumber('id');

    /*
    |--------------------------------------------------------------------------
    | MESSAGERIE UTILISATEUR
    |--------------------------------------------------------------------------
    */

    Route::get('/message/contacts', [MessageController::class, 'contacts']);

    Route::get('/conversation', [MessageController::class, 'inbox']);
    Route::post('/conversation/{otherUserId}', [MessageController::class, 'createConversation'])
        ->whereNumber('otherUserId');

    Route::get('/message/{conversation_id}', [MessageController::class, 'getMessages'])
        ->whereNumber('conversation_id');

    Route::post('/message/{conversation_id}', [MessageController::class, 'sendMessage'])
        ->whereNumber('conversation_id');
    Route::match(['put', 'patch'], '/message/{message_id}', [MessageController::class, 'updateMessage'])
        ->whereNumber('message_id');
    Route::post('/message/{message_id}/update', [MessageController::class, 'updateMessage'])
        ->whereNumber('message_id');
    Route::delete('/message/{message_id}', [MessageController::class, 'destroyMessage'])
        ->whereNumber('message_id');

    Route::post('/message/{message_id}/reaction', [MessageController::class, 'reactToMessage'])
        ->whereNumber('message_id');

    Route::delete('/message/{message_id}/reaction', [MessageController::class, 'removeReaction'])
        ->whereNumber('message_id');

    /*
    |--------------------------------------------------------------------------
    | ANCIENNES ROUTES MESSAGERIE GARDÉES TEMPORAIREMENT
    |--------------------------------------------------------------------------
    */

    Route::get('/conversation/{id}/create', [MessageController::class, 'createConversation'])
        ->whereNumber('id');

    Route::post('/message/{id}/send', [MessageController::class, 'sendMessage'])
        ->whereNumber('id');

    /*
    |--------------------------------------------------------------------------
    | DOCUMENTS UTILISATEUR
    |--------------------------------------------------------------------------
    */

    Route::get('/documents/my', [DocumentController::class, 'myDocuments']);
    Route::post('/documents', [DocumentController::class, 'store']);

    /*
    |--------------------------------------------------------------------------
    | PARCOURS CLIENT / COACH
    |--------------------------------------------------------------------------
    */

    Route::get('/clients/{client}/history', [ClientJourneyController::class, 'history'])
        ->whereNumber('client');

    Route::get('/clients/{client}/notes', [ClientJourneyController::class, 'notes'])
        ->whereNumber('client');

    Route::post('/clients/{client}/notes', [ClientJourneyController::class, 'storeNote'])
        ->whereNumber('client');

    Route::put('/client-notes/{note}', [ClientJourneyController::class, 'updateNote'])
        ->whereNumber('note');

    Route::patch('/client-notes/{note}', [ClientJourneyController::class, 'updateNote'])
        ->whereNumber('note');

    Route::delete('/client-notes/{note}', [ClientJourneyController::class, 'deleteNote'])
        ->whereNumber('note');

    Route::get('/clients/{client}/onboarding', [ClientJourneyController::class, 'showOnboarding'])
        ->whereNumber('client');

    Route::get('/client/onboarding', [ClientJourneyController::class, 'myOnboarding']);
    Route::put('/client/onboarding', [ClientJourneyController::class, 'saveMyOnboarding']);
    Route::patch('/client/onboarding', [ClientJourneyController::class, 'saveMyOnboarding']);

    Route::get('/fitness-assessment/form', [FitnessAssessmentController::class, 'activeForm']);
    Route::get('/fitness-assessment', [FitnessAssessmentController::class, 'mine']);
    Route::put('/fitness-assessment', [FitnessAssessmentController::class, 'saveMine']);
    Route::patch('/fitness-assessment', [FitnessAssessmentController::class, 'saveMine']);
    Route::get('/clients/{client}/fitness-assessments', [FitnessAssessmentController::class, 'byClient'])
        ->whereNumber('client');
    Route::put('/fitness-assessments/{assessment}/review', [FitnessAssessmentController::class, 'review'])
        ->whereNumber('assessment');
    Route::patch('/fitness-assessments/{assessment}/review', [FitnessAssessmentController::class, 'review'])
        ->whereNumber('assessment');

    /*
    |--------------------------------------------------------------------------
    | RÉSERVATION GÉNÉRIQUE
    |--------------------------------------------------------------------------
    */

    Route::get('/planning', [ReservationController::class, 'planning']);
    Route::get('/reservation/{id}', [ReservationController::class, 'show'])->whereNumber('id');
    Route::get('/reservation/{id}/calendar.ics', [ReservationController::class, 'calendar'])->whereNumber('id');

    /*
    |--------------------------------------------------------------------------
    | VISIO - Coach + participants
    |--------------------------------------------------------------------------
    */

    Route::get('/visio/my-sessions', [VisioSessionController::class, 'index']);
    Route::post('/visio/sessions', [VisioSessionController::class, 'store']);
    Route::put('/visio/sessions/{id}', [VisioSessionController::class, 'update'])->whereNumber('id');
    Route::patch('/visio/sessions/{id}', [VisioSessionController::class, 'update'])->whereNumber('id');
    Route::post('/visio/sessions/{id}/reserve', [VisioSessionController::class, 'reserve'])->whereNumber('id');
    Route::post('/visio/sessions/{id}/start', [VisioSessionController::class, 'start'])->whereNumber('id');
    Route::post('/visio/sessions/{id}/join', [VisioSessionController::class, 'join'])->whereNumber('id');
    Route::post('/visio/sessions/{id}/leave', [VisioSessionController::class, 'leave'])->whereNumber('id');
    Route::post('/visio/sessions/{id}/end', [VisioSessionController::class, 'end'])->whereNumber('id');
    Route::post('/visio/sessions/{id}/cancel', [VisioSessionController::class, 'cancel'])->whereNumber('id');
    Route::get('/visio/sessions/{id}/participants', [VisioSessionController::class, 'participants'])->whereNumber('id');

    Route::post('/visio/sessions/{id}/participants/{participantId}/paid', [VisioSessionController::class, 'markParticipantPaid'])
        ->whereNumber('id')
        ->whereNumber('participantId');
});

/*
|--------------------------------------------------------------------------
| ADMINISTRATION
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'is_admin'])->group(function () {
    /*
    |--------------------------------------------------------------------------
    | DASHBOARD ADMIN
    |--------------------------------------------------------------------------
    */

    Route::get('/admin/dashboard', [AdminController::class, 'dashboard']);

    /*
    |--------------------------------------------------------------------------
    | UTILISATEURS ADMIN
    |--------------------------------------------------------------------------
    */

    Route::get('/users', [AdminController::class, 'getUsers']);

    Route::post('/users', [AdminController::class, 'createUser']);
    Route::post('/admin/users', [AdminController::class, 'createUser']);

    Route::put('/users/{id}', [AdminController::class, 'updateUser'])->whereNumber('id');
    Route::patch('/users/{id}', [AdminController::class, 'updateUser'])->whereNumber('id');

    Route::delete('/users/{id}', [AdminController::class, 'deleteUser'])->whereNumber('id');

    Route::put('/users/{id}/validate', [AdminController::class, 'validateUser'])->whereNumber('id');
    Route::put('/users/{id}/siret/verify', [AdminController::class, 'verifySiret'])->whereNumber('id');

    /*
    |--------------------------------------------------------------------------
    | MESSAGERIE ADMIN
    |--------------------------------------------------------------------------
    */

    Route::get('/admin/messages', [AdminMessageController::class, 'index']);
    Route::get('/messages', [AdminMessageController::class, 'index']);

    Route::get('/admin/messages/coaches', [AdminMessageController::class, 'coaches']);
    Route::get('/messages/coaches', [AdminMessageController::class, 'coaches']);

    Route::post('/admin/messages/broadcast-coaches', [AdminMessageController::class, 'broadcastToCoaches']);
    Route::post('/messages/broadcast-coaches', [AdminMessageController::class, 'broadcastToCoaches']);
    Route::post('/admin/messages/send-to-coaches', [AdminMessageController::class, 'broadcastToCoaches']);
    Route::post('/messages/send-to-coaches', [AdminMessageController::class, 'broadcastToCoaches']);

    Route::post('/admin/messages', [AdminMessageController::class, 'store']);
    Route::post('/messages', [AdminMessageController::class, 'store']);

    Route::put('/admin/messages/{id}/read', [AdminMessageController::class, 'markAsRead'])->whereNumber('id');
    Route::post('/admin/messages/{id}/reply', [AdminMessageController::class, 'reply'])->whereNumber('id');

    Route::delete('/admin/messages/{id}', [AdminMessageController::class, 'destroy'])->whereNumber('id');

    /*
    |--------------------------------------------------------------------------
    | ANNONCES ADMIN
    |--------------------------------------------------------------------------
    */

    Route::get('/getAllAnnonce', [AnnonceController::class, 'getAllAnnonce']);
    Route::put('/annonces/{id}/valide', [AnnonceController::class, 'validerAnnonce'])->whereNumber('id');
    Route::put('/annonces/{id}/refuser', [AnnonceController::class, 'refuserAnnonce'])->whereNumber('id');
    Route::delete('/annonces/{id}', [AnnonceController::class, 'destroy'])->whereNumber('id');

    /*
    |--------------------------------------------------------------------------
    | DOCUMENTS ADMIN
    |--------------------------------------------------------------------------
    */

    Route::get('/documents', [DocumentController::class, 'index']);
    Route::delete('/documents/{id}', [DocumentController::class, 'destroy'])->whereNumber('id');
    Route::put('/documents/{id}/valider', [DocumentController::class, 'validerDocument'])->whereNumber('id');
    Route::put('/documents/{id}/refuser', [DocumentController::class, 'refuserDocument'])->whereNumber('id');

    Route::get('/admin/fitness-assessment/forms', [FitnessAssessmentController::class, 'forms']);
    Route::post('/admin/fitness-assessment/forms', [FitnessAssessmentController::class, 'storeForm']);
    Route::put('/admin/fitness-assessment/forms/{form}', [FitnessAssessmentController::class, 'updateForm'])
        ->whereNumber('form');
    Route::patch('/admin/fitness-assessment/forms/{form}', [FitnessAssessmentController::class, 'updateForm'])
        ->whereNumber('form');

    /*
    |--------------------------------------------------------------------------
    | RÉSERVATIONS ADMIN
    |--------------------------------------------------------------------------
    */

    Route::get('/reservation/all', [ReservationController::class, 'getAllReservation']);
    Route::post('/reservation/{id}/validate-prestation', [PayementController::class, 'validatePrestation'])->whereNumber('id');
    Route::post('/reservation/{id}/transfer-to-coach', [PayementController::class, 'transferToCoach'])->whereNumber('id');
    Route::post('/reservation/{id}/resolve-dispute', [PayementController::class, 'resolveDispute'])->whereNumber('id');
    Route::post('/reservation/{id}/refund', [PayementController::class, 'refundReservation'])->whereNumber('id');

    /*
    |--------------------------------------------------------------------------
    | PAIEMENTS ADMIN
    |--------------------------------------------------------------------------
    */

    Route::get('/admin/payments', [PayementController::class, 'index']);
    Route::get('/payments', [PayementController::class, 'index']);
    Route::get('/payements', [PayementController::class, 'index']);

    Route::get('/admin/commissions', [PayementController::class, 'commissionByIntervenant']);

    /*
    |--------------------------------------------------------------------------
    | PARAMÈTRES BUSINESS ADMIN
    |--------------------------------------------------------------------------
    */

    Route::get('/admin/business-settings', [AdminController::class, 'businessSettings']);
    Route::put('/admin/business-settings', [AdminController::class, 'updateBusinessSettings']);

    /*
    |--------------------------------------------------------------------------
    | AVIS ADMIN
    |--------------------------------------------------------------------------
    */

    Route::put('/admin/reviews/{id}/moderate', [ReviewController::class, 'moderate'])->whereNumber('id');
});

/*
|--------------------------------------------------------------------------
| INTERVENANTS
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'is_intervenant'])->group(function () {
    Route::get('/coach/credentials', [DocumentController::class, 'myCredentials']);
    Route::post('/coach/credentials', [DocumentController::class, 'storeCredential']);
    Route::delete('/coach/credentials/{id}', [DocumentController::class, 'destroy'])->whereNumber('id');

    Route::post('/annonces', [AnnonceController::class, 'store']);
    Route::put('/annonces/{id}', [AnnonceController::class, 'update'])->whereNumber('id');
    Route::post('/annonces/{id}/boost', [AnnonceController::class, 'boost'])->whereNumber('id');

    Route::get('/reservation/intervenant', [ReservationController::class, 'getReservationByIntervenant']);

    Route::put('/reservation/{id}/valider', [ReservationController::class, 'validerReservation'])->whereNumber('id');
    Route::put('/reservation/{id}/refuser', [ReservationController::class, 'refuserReservation'])->whereNumber('id');
    Route::put('/reservation/{id}/terminer', [ReservationController::class, 'terminerReservation'])->whereNumber('id');

    Route::get('/reservations/filter', [ReservationController::class, 'filterByStatus']);

    Route::post('/missions/{id}/apply', [MissionController::class, 'apply'])->whereNumber('id');

    Route::get('/intervenant/commission', [PayementController::class, 'myRevenue']);
});

/*
|--------------------------------------------------------------------------
| CLIENTS
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'is_client'])->group(function () {
    Route::put('/annonces/{id}/reserve', [AnnonceController::class, 'reserver'])->whereNumber('id');

    Route::get('/reservation/client', [ReservationController::class, 'getReservationByClient']);
    Route::match(['put', 'post'], '/reservation/{id}/reschedule', [ReservationController::class, 'reschedule'])
        ->whereNumber('id');
    Route::match(['put', 'patch'], '/reservation/{id}', [ReservationController::class, 'reschedule'])
        ->whereNumber('id');

    Route::post('/create-payment-intent', [PayementController::class, 'createPaymentIntent']);
    Route::get('/payment/status/{id}', [PayementController::class, 'checkPaymentStatus']);
    Route::post('/reservation/{id}/confirm-prestation', [PayementController::class, 'confirmPrestationByClient'])->whereNumber('id');
    Route::post('/reservation/{id}/dispute', [PayementController::class, 'disputePrestation'])->whereNumber('id');

    Route::post('/reservations/{id}/review', [ReviewController::class, 'store'])->whereNumber('id');
});

/*
|--------------------------------------------------------------------------
| STRUCTURES
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'is_structure'])->group(function () {
    Route::post('/missions', [MissionController::class, 'store']);
    Route::get('/structure/missions', [MissionController::class, 'myMissions']);

    Route::get('/missions/{id}/candidatures', [MissionController::class, 'candidatures'])->whereNumber('id');
    Route::put('/candidatures/{id}/accept', [MissionController::class, 'acceptCandidature'])->whereNumber('id');
});
