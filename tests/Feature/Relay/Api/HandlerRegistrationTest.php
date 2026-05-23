<?php

namespace Tests\Feature\Relay\Api;

use App\Models\HubRelayHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HandlerRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_can_register_and_list_handlers(): void
    {
        $client = $this->createRelayClient();

        $createResponse = $this->postJson('/api/v1/handlers', [
            'name' => 'SITREP Receiver',
            'endpoint_url' => 'https://local.app.test/hooks/relay',
            'message_type_pattern' => 'sitrep.*',
            'source_system' => 'sitrep.app',
            'auth_token' => 'secret-token',
        ], $this->relayHeaders($client->api_key));

        $createResponse->assertCreated()
            ->assertJsonPath('handler.name', 'SITREP Receiver')
            ->assertJsonPath('handler.message_type_pattern', 'sitrep.*');

        $handlerId = $createResponse->json('handler.id');

        $this->assertDatabaseHas('hub_relay_handlers', [
            'id' => $handlerId,
            'hub_relay_client_id' => $client->id,
            'endpoint_url' => 'https://local.app.test/hooks/relay',
        ]);

        $listResponse = $this->getJson('/api/v1/handlers', $this->relayHeaders($client->api_key));

        $listResponse->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $handlerId);
    }

    public function test_client_can_update_and_deactivate_own_handler(): void
    {
        $client = $this->createRelayClient();
        $handler = HubRelayHandler::query()->create([
            'hub_relay_client_id' => $client->id,
            'name' => 'Initial Handler',
            'endpoint_url' => 'https://local.app.test/hooks/relay',
            'message_type_pattern' => '*',
            'is_active' => true,
        ]);

        $updateResponse = $this->patchJson('/api/v1/handlers/'.$handler->id, [
            'message_type_pattern' => 'sitrep.record',
            'is_active' => false,
        ], $this->relayHeaders($client->api_key));

        $updateResponse->assertOk()
            ->assertJsonPath('handler.message_type_pattern', 'sitrep.record')
            ->assertJsonPath('handler.is_active', false);

        $deleteResponse = $this->deleteJson('/api/v1/handlers/'.$handler->id, [], $this->relayHeaders($client->api_key));

        $deleteResponse->assertOk()
            ->assertJsonPath('handler.is_active', false);
    }
}
