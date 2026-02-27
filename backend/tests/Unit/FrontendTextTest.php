<?php

namespace Tests\Unit;

use App\Models\FrontendText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FrontendTextTest extends TestCase
{
    use RefreshDatabase;

    public function test_is_active_is_cast_to_boolean(): void
    {
        $frontendText = FrontendText::create([
            'route_key' => 'auth.login',
            'text_key' => 'auth.title',
            'value' => 'WBT Generator',
            'is_active' => 1,
        ]);

        $this->assertIsBool($frontendText->is_active);
        $this->assertTrue($frontendText->is_active);
    }
}
