<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AnnonceController;
use App\Http\Controllers\ConnexionController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\InscriptionController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\PayementController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReservationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/
/*
|--------------------------------------------------------------------------
| AUTHENTIFICATION
|--------------------------------------------------------------------------
| Routes publiques liées à l'inscription, connexion et récupération de compte
*/

Route::post('/register', [InscriptionController::class, 'register']);
Route::post('/login', [ConnexionController::class, 'login']);
Route::post('/logout', [ConnexionController::class, 'logout'])->middleware('auth:sanctum');
Route::post('/forgot-password', [ConnexionController::class, 'forgotPassword']);
Route::post('/reset-password', [ConnexionController::class, 'resetPassword']);


/*
|--------------------------------------------------------------------------
| PROFIL UTILISATEUR (AUTH REQUIRED)
|--------------------------------------------------------------------------
| Gestion du profil utilisateur connecté
*/

Route::middleware('auth:sanctum')->group(function () {
        Route::get('/profile', [ProfileController::class, 'show']);
        Route::post('/profile/update', [ProfileController::class, 'update']);
});


/*
|--------------------------------------------------------------------------
| ADMINISTRATION
|--------------------------------------------------------------------------
| Routes accessibles uniquement aux administrateurs
*/

Route::middleware(['auth:sanctum', 'is_admin'])->group(function () {
    /*
    |--------------------------------------------------------------------------
    | GESTION DES UTILISATEURS (ADMIN)
    |--------------------------------------------------------------------------
    | Routes dédiées à la consultation et à la suppression des annonces
    | - Récupération de toutes les utilisateurs
    | - Suppression d’une utilisateurs spécifique
    */
       Route::get('/users', [AdminController::class, 'getUsers']);
    /*
    |--------------------------------------------------------------------------
    | GESTION DES ANNONCES (ADMIN)
    |--------------------------------------------------------------------------
    | Routes dédiées à la consultation et à la suppression des annonces
    | - Récupération de toutes les annonces
    | - Suppression d’une annonce spécifique
    */
        Route::get('/getAllAnnonce', [AnnonceController::class, 'getAllAnnonce']);
        Route::put('/annonces/{id}/valide', [AnnonceController::class, 'validerAnnonce']);
        Route::put('/annonces/{id}/refuser', [AnnonceController::class, 'refuserAnnonce']);
        Route::delete('/annonces/{id}', [AnnonceController::class, 'destroy']);
    /*
    |--------------------------------------------------------------------------
    | GESTION DES DOCUMENTS (ADMIN)
    |--------------------------------------------------------------------------
    | Routes dédiées à la consultation et à la suppression des annonces
    | - Récupération de toutes les documents
    | - Suppression d’une documents spécifique
    */
        Route::get('/documents', [DocumentController::class, 'index']);
        Route::get('/documents/delete/{id}', [DocumentController::class, 'destroy']);
        Route::put('/documents/{id}/valider', [DocumentController::class, 'validerDocument']);
        Route::put('/documents/{id}/refuser', [DocumentController::class, 'refuserDocument']);

    /*
    |--------------------------------------------------------------------------
    | GESTION DES RESERVATIONS (ADMIN)
    |--------------------------------------------------------------------------
    | Routes dédiées à la consultation et à la suppression des annonces
    | - Récupération de toutes les documents
    | - Suppression d’une documents spécifique
    */
       Route::get('/reservation/all', [ReservationController::class, 'getAllReservation']);
    /*
    |--------------------------------------------------------------------------
    | GESTION DES PAYEMENTS (ADMIN)
    |--------------------------------------------------------------------------
    | Routes dédiées à la consultation et à la suppression des annonces
    | - Récupération de toutes les documents
    | - Suppression d’une documents spécifique
    */
       Route::get('/admin/payments', [PayementController::class, 'index']);
       Route::get('admin/commissions', [PayementController::class, 'commissionByIntervenant']);
});


/*
|--------------------------------------------------------------------------
| INTERVENANTS
|--------------------------------------------------------------------------
| Routes dédiées aux intervenants (création et gestion des annonces)
*/

Route::middleware(['auth:sanctum', 'is_intervenant'])->group(function () {
    /*
    |--------------------------------------------------------------------------
    | GESTION DES ANNONCES (INTERVENANTS)
    |--------------------------------------------------------------------------
    | Routes dédiées à la consultation et à la suppression des annonces
    | - Récupération de toutes les annonces
    | - Suppression d’une annonce spécifique
    */
        Route::post('/annonces', [AnnonceController::class, 'store']);
        Route::put('/annonces/{id}', [AnnonceController::class, 'update']);
        Route::get('/annonces/{id}/detail', [AnnonceController::class, 'detailAnnonce']);
    /*
    |--------------------------------------------------------------------------
    | GESTION DES DOCUMENTS (INTERVENANTS)
    |--------------------------------------------------------------------------
    | Routes dédiées à la consultation et à la suppression des annonces
    | - Récupération de toutes les annonces
    | - Suppression d’une annonce spécifique
    */
        Route::post('/documents', [DocumentController::class, 'store']);
    /*
    |--------------------------------------------------------------------------
    | GESTION DES RESERVATIONS (INTERVENANTS)
    |--------------------------------------------------------------------------
    | Routes dédiées à la consultation et à la suppression des annonces
    | - Récupération de toutes les documents
    | - Suppression d’une documents spécifique
    */
       Route::get('/reservation/intervenant', [ReservationController::class, 'getReservationByIntervenant']);
       Route::put('/reservation/{id}/valider', [ReservationController::class, 'validerReservation']);
       Route::put('/reservation/{id}/refuser', [ReservationController::class, 'refuserReservation']);
       Route::put('/reservation/{id}/terminer', [ReservationController::class, 'terminerReservation']);
       Route::get('/reservations/filter', [ReservationController::class, 'filterByStatus']);
    /*
    |--------------------------------------------------------------------------
    | GESTION DES CONVERSATIONS (INTERVENANTS)
    |--------------------------------------------------------------------------
    | Routes dédiées à la consultation et à la suppression des annonces
    | - Récupération de toutes les documents
    | - Suppression d’une documents spécifique
    */
       Route::get('/message/{id}', [MessageController::class, 'getMessages']);
       Route::get('/conversation', [MessageController::class, 'inbox']);
       Route::post('/message/{id}/send', [MessageController::class, 'sendMessage']);
       Route::get('/conversation/{id}/create', [MessageController::class, 'createConversation']);
    /*
    |--------------------------------------------------------------------------
    | GESTION DES PAYEMENTS (INTERVENANTS)
    |--------------------------------------------------------------------------
    | Routes dédiées à la consultation et à la suppression des annonces
    | - Récupération de toutes les documents
    | - Suppression d’une documents spécifique
    */
       Route::get('/intervenant/commission', [PayementController::class, 'myRevenue']);
});


/*
|--------------------------------------------------------------------------
| CLIENTS
|--------------------------------------------------------------------------
| Actions disponibles pour les clients
*/

Route::middleware(['auth:sanctum', 'is_client'])->group(function () {
    /*
    |--------------------------------------------------------------------------
    | GESTION DES ANNONCES (CLIENTS)
    |--------------------------------------------------------------------------
    | Routes dédiées à la consultation et à la suppression des annonces
    | - Récupération de toutes les annonces
    | - Suppression d’une annonce spécifique
    */
         Route::put('/annonces/{id}/reserve', [AnnonceController::class, 'reserver']);
         Route::get('/annonces/{id}/detail', [AnnonceController::class, 'detailAnnonce']);
    /*
    /*
    |--------------------------------------------------------------------------
    | GESTION DES DOCUMENTS (CLIENTS)
    |--------------------------------------------------------------------------
    | Routes dédiées à la consultation et à la suppression des annonces
    | - Récupération de toutes les annonces
    | - Suppression d’une annonce spécifique
    */
    Route::post('/documents', [DocumentController::class, 'store']);
    /*
    |--------------------------------------------------------------------------
    | GESTION DES RESERVATIONS (CLIENTS)
    |--------------------------------------------------------------------------
    | Routes dédiées à la consultation et à la suppression des annonces
    | - Récupération de toutes les documents
    | - Suppression d’une documents spécifique
    */
        Route::get('/reservation/client', [ReservationController::class, 'getReservationByClient']);
    /*
    |--------------------------------------------------------------------------
    | GESTION DES CONVERSATIONS (CLIENTS)
    |--------------------------------------------------------------------------
    | Routes dédiées à la consultation et à la suppression des annonces
    | - Récupération de toutes les documents
    | - Suppression d’une documents spécifique
    */
       Route::get('/conversation', [MessageController::class, 'inbox']);
    /*
    |--------------------------------------------------------------------------
    | GESTION DES PAYEMENTS (CLIENTS)
    |--------------------------------------------------------------------------
    | Routes dédiées à la consultation et à la suppression des annonces
    | - Récupération de toutes les documents
    | - Suppression d’une documents spécifique
    */
       Route::post('/create-payment-intent', [PayementController::class, 'createPaymentIntent']);
       Route::post('/payment/webhook', [PayementController::class, 'handleWebhook']);
       Route::get('/payment/status/{id}', [PayementController::class, 'checkPaymentStatus']);
});




