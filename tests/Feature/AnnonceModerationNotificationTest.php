<?php

namespace Tests\Feature;

use App\Models\Annonce;
use App\Models\Role;
use App\Models\User;
use App\Notifications\AnnonceStatusNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AnnonceModerationNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function member(string $role): User
    {
        $user = User::factory()->create(['account_status' => 'approved']);
        $user->roles()->attach(Role::firstOrCreate(
            ['slug' => $role],
            ['name' => ucfirst($role), 'is_active' => true]
        ));

        return $user;
    }

    private function pendingAnnonce(User $owner): Annonce
    {
        return Annonce::create([
            'user_id' => $owner->id,
            'titre' => 'Coaching personnalisé en visio',
            'contenu' => 'Une séance adaptée aux objectifs du client.',
            'status' => 'en_attente',
            'announcement_type' => 'coach_service',
            'price' => '45.00',
            'duration' => 60,
            'available_days' => ['monday'],
            'available_hours' => ['09:00-12:00'],
        ]);
    }

    public function test_approval_sends_one_publication_email_to_the_owner(): void
    {
        Notification::fake();
        $owner = $this->member('intervenant');
        $annonce = $this->pendingAnnonce($owner);
        Sanctum::actingAs($this->member('admin'));

        $this->putJson("/api/annonces/{$annonce->id}/valide")->assertOk();
        $this->putJson("/api/annonces/{$annonce->id}/valide")->assertOk();

        Notification::assertSentToTimes($owner, AnnonceStatusNotification::class, 1);
        Notification::assertSentTo($owner, AnnonceStatusNotification::class, function ($notification) use ($annonce, $owner) {
            $mail = $notification->toMail($owner);

            return $mail->subject === 'Votre annonce GotFit est publiée'
                && str_ends_with($mail->actionUrl, '/annonces/'.$annonce->id);
        });
    }

    public function test_refusal_sends_one_email_to_the_owner(): void
    {
        Notification::fake();
        $owner = $this->member('client');
        $annonce = $this->pendingAnnonce($owner);
        Sanctum::actingAs($this->member('admin'));

        $this->putJson("/api/annonces/{$annonce->id}/refuser")->assertOk();
        $this->putJson("/api/annonces/{$annonce->id}/refuser")->assertOk();

        Notification::assertSentToTimes($owner, AnnonceStatusNotification::class, 1);
        Notification::assertSentTo($owner, AnnonceStatusNotification::class, function ($notification) use ($owner) {
            $mail = $notification->toMail($owner);

            return $mail->subject === 'Votre annonce GotFit a été refusée'
                && str_ends_with($mail->actionUrl, '/annonces/mes-annonces');
        });
    }
}
