<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AnnonceController;
use App\Http\Controllers\ConnexionController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\InscriptionController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\MissionController;
use App\Http\Controllers\PayementController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\ReviewController;
use Illuminate\Support\Facades\Route;

/* AUTH */
Route::post('/register', [InscriptionController::class, 'register']);
Route::post('/login', [ConnexionController::class, 'login']);
Route::post('/logout', [ConnexionController::class, 'logout'])->middleware('auth:sanctum');
Route::post('/forgot-password', [ConnexionController::class, 'forgotPassword']);
Route::post('/reset-password', [ConnexionController::class, 'resetPassword']);

/* Stripe webhook : public, Stripe ne peut pas envoyer de token Sanctum */
Route::post('/payment/webhook', [PayementController::class, 'handleWebhook']);

/* ROUTES CONNECTÉES COMMUNES */
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::post('/profile/update', [ProfileController::class, 'update']);

    Route::get('/annonces', [AnnonceController::class, 'index']);
    Route::get('/annonces/{id}/detail', [AnnonceController::class, 'detailAnnonce']);
    Route::get('/intervenants/{id}/reviews', [ReviewController::class, 'byIntervenant']);

    Route::get('/favorites', [FavoriteController::class, 'index']);
    Route::post('/favorites/{annonce}', [FavoriteController::class, 'store']);
    Route::delete('/favorites/{id}', [FavoriteController::class, 'destroy']);
    Route::delete('/favorites/annonce/{annonce}', [FavoriteController::class, 'destroyByAnnonce']);

    Route::get('/conversation', [MessageController::class, 'inbox']);
    Route::get('/conversation/{id}/create', [MessageController::class, 'createConversation']);
    Route::get('/message/{id}', [MessageController::class, 'getMessages']);
    Route::post('/message/{id}/send', [MessageController::class, 'sendMessage']);

    Route::get('/missions', [MissionController::class, 'index']);
    Route::get('/reservation/{id}', [ReservationController::class, 'show']);

    Route::get('/documents/my', [DocumentController::class, 'myDocuments']);
    Route::post('/documents', [DocumentController::class, 'store']);
});

/* ADMINISTRATION */
Route::middleware(['auth:sanctum', 'is_admin'])->group(function () {
    Route::get('/admin/dashboard', [AdminController::class, 'dashboard']);
    Route::get('/users', [AdminController::class, 'getUsers']);
    Route::post('/admin/users', [AdminController::class, 'createUser']);
    Route::put('/users/{id}/validate', [AdminController::class, 'validateUser']);

    Route::get('/getAllAnnonce', [AnnonceController::class, 'getAllAnnonce']);
    Route::put('/annonces/{id}/valide', [AnnonceController::class, 'validerAnnonce']);
    Route::put('/annonces/{id}/refuser', [AnnonceController::class, 'refuserAnnonce']);
    Route::delete('/annonces/{id}', [AnnonceController::class, 'destroy']);

    Route::get('/documents', [DocumentController::class, 'index']);
    Route::delete('/documents/{id}', [DocumentController::class, 'destroy']);
    Route::put('/documents/{id}/valider', [DocumentController::class, 'validerDocument']);
    Route::put('/documents/{id}/refuser', [DocumentController::class, 'refuserDocument']);

    Route::get('/reservation/all', [ReservationController::class, 'getAllReservation']);

    Route::get('/admin/payments', [PayementController::class, 'index']);
    Route::get('/admin/commissions', [PayementController::class, 'commissionByIntervenant']);
    Route::get('/admin/business-settings', [AdminController::class, 'businessSettings']);
    Route::put('/admin/business-settings', [AdminController::class, 'updateBusinessSettings']);

    Route::put('/admin/reviews/{id}/moderate', [ReviewController::class, 'moderate']);
});

/* INTERVENANTS */
Route::middleware(['auth:sanctum', 'is_intervenant'])->group(function () {
    Route::post('/annonces', [AnnonceController::class, 'store']);
    Route::put('/annonces/{id}', [AnnonceController::class, 'update']);
    Route::post('/annonces/{id}/boost', [AnnonceController::class, 'boost']);

    Route::get('/reservation/intervenant', [ReservationController::class, 'getReservationByIntervenant']);
    Route::put('/reservation/{id}/valider', [ReservationController::class, 'validerReservation']);
    Route::put('/reservation/{id}/refuser', [ReservationController::class, 'refuserReservation']);
    Route::put('/reservation/{id}/terminer', [ReservationController::class, 'terminerReservation']);
    Route::get('/reservations/filter', [ReservationController::class, 'filterByStatus']);

    Route::post('/missions/{id}/apply', [MissionController::class, 'apply']);

    Route::get('/intervenant/commission', [PayementController::class, 'myRevenue']);
});

/* CLIENTS */
Route::middleware(['auth:sanctum', 'is_client'])->group(function () {
    Route::put('/annonces/{id}/reserve', [AnnonceController::class, 'reserver']);
    Route::get('/reservation/client', [ReservationController::class, 'getReservationByClient']);
    Route::post('/create-payment-intent', [PayementController::class, 'createPaymentIntent']);
    Route::get('/payment/status/{id}', [PayementController::class, 'checkPaymentStatus']);
    Route::post('/reservations/{id}/review', [ReviewController::class, 'store']);
});

/* STRUCTURES */
Route::middleware(['auth:sanctum', 'is_structure'])->group(function () {
    Route::post('/missions', [MissionController::class, 'store']);
    Route::get('/structure/missions', [MissionController::class, 'myMissions']);
    Route::get('/missions/{id}/candidatures', [MissionController::class, 'candidatures']);
    Route::put('/candidatures/{id}/accept', [MissionController::class, 'acceptCandidature']);
});
