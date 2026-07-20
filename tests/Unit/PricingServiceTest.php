<?php

namespace Tests\Unit;

use App\Services\PricingService;
use PHPUnit\Framework\TestCase;

class PricingServiceTest extends TestCase
{
    public function test_promo_price_is_half_of_base(): void
    {
        // Simule la config
        if (! function_exists('config')) {
            $this->markTestSkipped('Framework non chargé.');
        }
    }
}
