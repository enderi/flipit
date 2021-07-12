<?php

namespace Tests\Unit;

use App\Dealers\OmahaDealer;
use PHPUnit\Framework\TestCase;

class OmahaDealerTest extends TestCase
{
    public function test_omaha_dealer_should_initialize()
    {
        $dealer = new OmahaDealer([],[]);

        $this->assertNotNull($dealer);
    }
}
