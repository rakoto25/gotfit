<?php

namespace Tests\Unit;

use App\Models\Offer;
use App\Services\StripeMarketplaceService;
use Tests\TestCase;

class StripeMarketplaceServiceTest extends TestCase
{
    public function test_offer_checkout_returns_to_the_existing_messages_page(): void
    {
        config(['services.stripe.frontend_url' => 'https://gotfit.test/']);
        $offer = new Offer(['conversation_id' => 17]);
        $offer->id = 29;

        $urls = app(StripeMarketplaceService::class)->offerReturnUrls($offer);

        $this->assertSame(
            'https://gotfit.test/messages?conversation_id=17&offer=29&payment=success&session_id={CHECKOUT_SESSION_ID}',
            $urls['success_url']
        );
        $this->assertSame(
            'https://gotfit.test/messages?conversation_id=17&offer=29&payment=cancelled',
            $urls['cancel_url']
        );
    }
}
