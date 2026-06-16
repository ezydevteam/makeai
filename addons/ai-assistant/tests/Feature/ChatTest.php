<?php

namespace Addons\AiAssistant\Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_chat_api_returns_ready(): void
    {
        $user = User::factory()->create();
        
        $response = $this->actingAs($user)->postJson(route('addon.ai.user.chat'), [
            'message' => 'Hello',
            'session_id' => 'test-session',
        ]);

        $response->assertStatus(200);
        $this->assertStringContainsString('READY', $response->streamedContent());
    }
}
