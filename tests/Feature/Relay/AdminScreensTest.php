<?php

namespace Tests\Feature\Relay;

use App\Models\HubRelayDelivery;
use App\Models\HubRelayClient;
use App\Models\HubRelayHandler;
use App\Models\HubRelayHandlerDispatch;
use App\Models\HubRelayMessage;
use App\Models\HubRelayReceipt;
use App\Models\HubRelayUploadSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AdminScreensTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sections_render(): void
    {
        $this->actingAs($this->createRelayUser());

        $message = HubRelayMessage::factory()->create([
            'source_hub_id' => 'barangay-hub',
            'source_system' => 'sitrep.app',
            'message_type' => 'sitrep.record',
        ]);

        $delivery = HubRelayDelivery::create([
            'hub_relay_message_id' => $message->id,
            'target_hub_id' => 'city-hub',
            'status' => HubRelayDelivery::STATUS_DEAD,
            'attempt_count' => 3,
            'last_error' => 'Timeout',
        ]);

        $receipt = HubRelayReceipt::create([
            'relay_id' => $message->relay_id,
            'source_hub_id' => 'city-hub',
            'message_type' => 'sitrep.record',
            'status' => HubRelayReceipt::STATUS_PROCESSED,
            'received_at' => now(),
            'processed_at' => now(),
        ]);

        $handler = HubRelayHandler::create([
            'hub_relay_client_id' => $this->createRelayClient()->id,
            'name' => 'SITREP Webhook',
            'endpoint_url' => 'https://local.app.test/hooks/relay',
            'message_type_pattern' => 'sitrep.*',
            'is_active' => true,
        ]);

        HubRelayHandlerDispatch::create([
            'hub_relay_handler_id' => $handler->id,
            'hub_relay_message_id' => $message->id,
            'hub_relay_receipt_id' => $receipt->id,
            'status' => HubRelayHandlerDispatch::STATUS_DEAD,
            'attempt_count' => 3,
            'last_error' => 'Webhook timeout',
            'queued_at' => now(),
            'failed_at' => now(),
        ]);

        HubRelayUploadSession::create([
            'hub_relay_message_id' => $message->id,
            'direction' => HubRelayUploadSession::DIRECTION_LOCAL_OUTBOUND,
            'source_hub_id' => 'barangay-hub',
            'target_hub_id' => 'city-hub',
            'attachment_name' => 'report.txt',
            'mime_type' => 'text/plain',
            'size_bytes' => 12,
            'chunk_size_bytes' => 6,
            'transferred_bytes' => 6,
            'transfer_progress_percent' => 50,
            'current_chunk_index' => 1,
            'transfer_status' => HubRelayUploadSession::STATUS_UPLOADING,
            'storage_disk' => 'local',
            'temp_path' => 'relay_uploads/tmp/test',
            'last_activity_at' => now(),
        ]);

        $this->get('/relay/outbox')->assertOk()->assertSee('Outbox');
        $this->get('/relay/inbox')->assertOk()->assertSee('Inbox');
        $this->get('/relay/deliveries')->assertOk()->assertSee('Deliveries');
        $this->get('/relay/uploads')->assertOk()->assertSee('Uploads');
        $this->get('/relay/dead-letters')->assertOk()->assertSee('Dead Letters');
        $this->get('/relay/clients')->assertOk()->assertSee('Clients');
        $this->get('/relay/users')->assertOk()->assertSee('Users');
        $this->get('/relay/messages/'.$message->id)->assertOk()->assertSee('Message Detail');
        $this->get('/relay/delivery/'.$delivery->id)->assertOk()->assertSee('Delivery Detail');

        $this->getJson('/relay/data/sections/deliveries')
            ->assertOk()
            ->assertJsonPath('rows.0.target_hq_hub_id', 'city-hub');

        $this->getJson('/relay/data/messages/'.$message->id)
            ->assertOk()
            ->assertJsonPath('subtitle', $message->relay_id);

        $this->getJson('/relay/data/deliveries/'.$delivery->id)
            ->assertOk()
            ->assertJsonPath('actions.0.label', 'Retry Delivery');
    }

    public function test_client_token_management_flows_render_and_update_state(): void
    {
        $this->actingAs($this->createRelayUser());

        $createResponse = $this->post('/relay/clients', [
            'name' => 'SITREP Client',
            'system_code' => 'sitrep.app',
            'description' => 'Primary SITREP integration',
        ]);

        $client = HubRelayClient::query()->where('system_code', 'sitrep.app')->firstOrFail();

        $createResponse
            ->assertRedirect('/relay/client/'.$client->id)
            ->assertSessionHas('generated_api_key');

        $originalApiKey = $client->api_key;

        $this->getJson('/api/v1/handlers', [
            'X-Relay-Key' => $originalApiKey,
        ])->assertOk();

        $this->get('/relay/client/'.$client->id)
            ->assertOk()
            ->assertSee('Client Detail')
            ->assertSee('Search handlers')
            ->assertDontSee('Raw Detail');

        $this->getJson('/relay/data/clients/'.$client->id)
            ->assertOk()
            ->assertJsonPath('subtitle', 'sitrep.app')
            ->assertJsonPath('extra.rows', []);

        $this->post('/relay/clients/'.$client->id.'/handlers', [
            'name' => 'SITREP Webhook',
            'endpoint_url' => 'https://sitrep.local/hooks/relay',
            'message_type_pattern' => 'sitrep.*',
            'source_system' => 'sitrep.app',
            'source_hub_id' => 'barangay-hub',
            'is_active' => '1',
        ])->assertRedirect('/relay/client/'.$client->id);

        $handler = HubRelayHandler::query()->where('hub_relay_client_id', $client->id)->firstOrFail();

        $this->post('/relay/clients/'.$client->id.'/handlers/'.$handler->id, [
            'name' => 'Updated SITREP Webhook',
            'endpoint_url' => 'https://sitrep.local/hooks/relay-v2',
            'message_type_pattern' => 'sitrep.record',
            'source_system' => 'sitrep.app',
            'source_hub_id' => 'city-hub',
            'is_active' => '1',
        ])->assertRedirect('/relay/client/'.$client->id);

        $this->assertSame('Updated SITREP Webhook', $handler->fresh()->name);

        $this->post('/relay/clients/'.$client->id.'/handlers/'.$handler->id.'/toggle-active')
            ->assertRedirect('/relay/client/'.$client->id);

        $this->assertFalse($handler->fresh()->is_active);

        $this->getJson('/relay/data/clients/'.$client->id)
            ->assertOk()
            ->assertJsonPath('extra.rows.0.name', 'Updated SITREP Webhook');

        $rotateResponse = $this->post('/relay/clients/'.$client->id.'/rotate-key');

        $client->refresh();

        $rotateResponse
            ->assertRedirect('/relay/client/'.$client->id)
            ->assertSessionHas('generated_api_key');

        $rotatedApiKey = $client->api_key;

        $this->assertNotSame($originalApiKey, $client->api_key);
        $this->assertTrue($client->is_active);

        $this->getJson('/api/v1/handlers', [
            'X-Relay-Key' => $originalApiKey,
        ])->assertStatus(401)
            ->assertJsonPath('error', 'Invalid relay client credentials');

        $this->getJson('/api/v1/handlers', [
            'X-Relay-Key' => $rotatedApiKey,
        ])->assertOk();

        $this->post('/relay/clients/'.$client->id.'/toggle-active')
            ->assertRedirect('/relay/client/'.$client->id);

        $this->assertFalse($client->fresh()->is_active);

        $this->getJson('/api/v1/handlers', [
            'X-Relay-Key' => $rotatedApiKey,
        ])->assertStatus(401)
            ->assertJsonPath('error', 'Invalid relay client credentials');

        $this->post('/relay/clients/'.$client->id.'/toggle-active')
            ->assertRedirect('/relay/client/'.$client->id);

        $this->assertTrue($client->fresh()->is_active);

        $this->getJson('/api/v1/handlers', [
            'X-Relay-Key' => $rotatedApiKey,
        ])->assertOk();

        $jsonRotateResponse = $this->postJson('/relay/clients/'.$client->id.'/rotate-key', [], [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $client->refresh();

        $jsonRotateResponse
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('status_message', 'API key rotated. The previous key is no longer valid.');

        $this->postJson('/relay/clients/'.$client->id.'/toggle-active', [], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('status_message', 'Client deactivated.');

        $this->assertFalse($client->fresh()->is_active);
    }

    public function test_user_management_flows_render_and_update_state(): void
    {
        $admin = $this->createRelayUser([
            'email' => 'admin@relay.test',
        ]);

        $this->actingAs($admin);

        $createResponse = $this->post('/relay/users', [
            'name' => 'Relay Operator',
            'email' => 'operator@relay.test',
            'role' => 'operator',
            'password' => 'relaypass123',
        ]);

        $user = \App\Models\User::query()->where('email', 'operator@relay.test')->firstOrFail();

        $createResponse->assertRedirect('/relay/user/'.$user->id);

        $this->get('/relay/user/'.$user->id)
            ->assertOk()
            ->assertSee('User Detail');

        $this->getJson('/relay/data/users/'.$user->id)
            ->assertOk()
            ->assertJsonPath('subtitle', 'operator@relay.test');

        $this->post('/relay/users/'.$user->id.'/role', [
            'role' => 'admin',
        ])->assertRedirect('/relay/user/'.$user->id);

        $this->assertSame('admin', $user->fresh()->role);

        $this->post('/relay/users/'.$user->id.'/toggle-active')
            ->assertRedirect('/relay/user/'.$user->id);

        $this->assertFalse($user->fresh()->is_active);

        $jsonCreateResponse = $this->postJson('/relay/users', [
            'name' => 'Relay Admin Two',
            'email' => 'admin2@relay.test',
            'role' => 'admin',
            'password' => 'relaypass456',
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $jsonUser = \App\Models\User::query()->where('email', 'admin2@relay.test')->firstOrFail();

        $jsonCreateResponse
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('status_message', 'Relay user created.')
            ->assertJsonPath('redirect_url', '/relay/user/'.$jsonUser->id);

        $this->postJson('/relay/users/'.$user->id.'/role', [
            'role' => 'operator',
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('status_message', 'Relay user role updated.');

        $this->assertSame('operator', $user->fresh()->role);

        $this->postJson('/relay/users/'.$user->id.'/toggle-active', [], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('status_message', 'Relay user reactivated.');

        $this->assertTrue($user->fresh()->is_active);

        $this->postJson('/relay/users/'.$user->id.'/reset-password', [], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('status_message', 'Relay user password reset.')
            ->assertJsonStructure([
                'generated_password',
            ]);
    }

    public function test_authenticated_operator_can_update_own_account_profile_and_password(): void
    {
        $user = $this->createRelayUser([
            'name' => 'Relay Operator',
            'email' => 'operator@relay.test',
            'password' => bcrypt('relaypass123'),
            'role' => \App\Models\User::ROLE_OPERATOR,
        ]);

        $this->actingAs($user);

        $this->postJson('/api/user', [
            'name' => 'Updated Relay Operator',
            'email' => 'updated.operator@relay.test',
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('status_message', 'Account details updated.')
            ->assertJsonPath('account.name', 'Updated Relay Operator')
            ->assertJsonPath('account.email', 'updated.operator@relay.test');

        $this->assertSame('Updated Relay Operator', $user->fresh()->name);
        $this->assertSame('updated.operator@relay.test', $user->fresh()->email);

        $this->postJson('/api/user/password', [
            'current_password' => 'relaypass123',
            'password' => 'relaypass456',
            'password_confirmation' => 'relaypass456',
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('status_message', 'Password updated.');

        $this->assertTrue(Hash::check('relaypass456', $user->fresh()->password));

        $this->postJson('/api/user/password', [
            'current_password' => 'wrong-password',
            'password' => 'relaypass789',
            'password_confirmation' => 'relaypass789',
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['current_password']);
    }

    public function test_relay_admin_routes_require_authenticated_operator(): void
    {
        $this->get('/relay')->assertRedirect('/relay/login');
        $this->get('/relay/clients')->assertRedirect('/relay/login');
        $this->get('/relay/users')->assertRedirect('/relay/login');
        $this->get('/relay/login')->assertRedirect('/?login=1');

        $this->getJson('/relay/data/dashboard', [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertStatus(401)
            ->assertJsonPath('session_expired', true);
    }

    public function test_non_admin_operator_cannot_manage_clients_or_users(): void
    {
        $this->actingAs($this->createRelayUser([
            'role' => \App\Models\User::ROLE_OPERATOR,
        ]));

        $client = $this->createRelayClient();
        $handler = HubRelayHandler::create([
            'hub_relay_client_id' => $client->id,
            'name' => 'Blocked Handler',
            'endpoint_url' => 'https://local.app.test/hooks/relay',
            'message_type_pattern' => 'blocked.*',
            'is_active' => true,
        ]);
        $user = $this->createRelayUser([
            'email' => 'another@relay.test',
        ]);

        $this->post('/relay/clients/'.$client->id.'/rotate-key')->assertForbidden();
        $this->post('/relay/clients/'.$client->id.'/handlers', [
            'name' => 'Should Fail',
            'endpoint_url' => 'https://local.app.test/hooks/relay',
        ])->assertForbidden();
        $this->post('/relay/clients/'.$client->id.'/handlers/'.$handler->id)->assertForbidden();
        $this->post('/relay/clients/'.$client->id.'/handlers/'.$handler->id.'/toggle-active')->assertForbidden();
        $this->post('/relay/users/'.$user->id.'/role', [
            'role' => 'admin',
        ])->assertForbidden();
    }

    public function test_operator_can_retry_delivery_and_handler_dispatch_from_detail_routes(): void
    {
        Queue::fake();

        $this->actingAs($this->createRelayUser([
            'role' => \App\Models\User::ROLE_OPERATOR,
        ]));

        $message = HubRelayMessage::factory()->create();

        $delivery = HubRelayDelivery::create([
            'hub_relay_message_id' => $message->id,
            'target_hub_id' => 'city-hub',
            'status' => HubRelayDelivery::STATUS_FAILED,
            'attempt_count' => 2,
            'last_error' => 'Timeout',
        ]);

        $client = $this->createRelayClient();
        $handler = HubRelayHandler::create([
            'hub_relay_client_id' => $client->id,
            'name' => 'SITREP Webhook',
            'endpoint_url' => 'https://local.app.test/hooks/relay',
            'message_type_pattern' => 'sitrep.*',
            'is_active' => true,
        ]);
        $receipt = HubRelayReceipt::create([
            'relay_id' => $message->relay_id,
            'source_hub_id' => 'city-hub',
            'message_type' => $message->message_type,
            'status' => HubRelayReceipt::STATUS_PROCESSED,
            'received_at' => now(),
            'processed_at' => now(),
        ]);
        $dispatch = HubRelayHandlerDispatch::create([
            'hub_relay_handler_id' => $handler->id,
            'hub_relay_message_id' => $message->id,
            'hub_relay_receipt_id' => $receipt->id,
            'status' => HubRelayHandlerDispatch::STATUS_DEAD,
            'attempt_count' => 3,
            'last_error' => 'Webhook timeout',
            'queued_at' => now(),
            'failed_at' => now(),
        ]);

        $this->post('/relay/delivery/'.$delivery->id.'/retry')
            ->assertRedirect('/relay/delivery/'.$delivery->id);
        $this->assertSame(HubRelayDelivery::STATUS_QUEUED, $delivery->fresh()->status);

        $this->post('/relay/handler-dispatch/'.$dispatch->id.'/retry')
            ->assertRedirect('/relay/handler-dispatch/'.$dispatch->id);
        $this->assertSame(HubRelayHandlerDispatch::STATUS_QUEUED, $dispatch->fresh()->status);

        $jsonDelivery = HubRelayDelivery::create([
            'hub_relay_message_id' => $message->id,
            'target_hub_id' => 'province-hub',
            'status' => HubRelayDelivery::STATUS_FAILED,
            'attempt_count' => 1,
            'last_error' => 'Retry me again',
        ]);

        $jsonDispatch = HubRelayHandlerDispatch::create([
            'hub_relay_handler_id' => $handler->id,
            'hub_relay_message_id' => $message->id,
            'hub_relay_receipt_id' => $receipt->id,
            'status' => HubRelayHandlerDispatch::STATUS_DEAD,
            'attempt_count' => 2,
            'last_error' => 'Retry me again',
            'queued_at' => now(),
            'failed_at' => now(),
        ]);

        $this->postJson('/relay/delivery/'.$jsonDelivery->id.'/retry', [], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('status_message', 'Delivery requeued for processing.');

        $this->assertSame(HubRelayDelivery::STATUS_QUEUED, $jsonDelivery->fresh()->status);

        $jsonDelivery->forceFill([
            'status' => HubRelayDelivery::STATUS_FAILED,
            'last_error' => 'Cancel me',
        ])->save();

        $this->postJson('/relay/delivery/'.$jsonDelivery->id.'/cancel', [], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('status_message', 'Delivery marked as dead.');

        $this->assertSame(HubRelayDelivery::STATUS_DEAD, $jsonDelivery->fresh()->status);

        $this->postJson('/relay/handler-dispatch/'.$jsonDispatch->id.'/retry', [], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('status_message', 'Handler dispatch requeued.');

        $this->assertSame(HubRelayHandlerDispatch::STATUS_QUEUED, $jsonDispatch->fresh()->status);
    }

    public function test_browser_session_api_matches_reference_contract(): void
    {
        $user = $this->createRelayUser([
            'email' => 'session@relay.test',
            'password' => bcrypt('relaypass123'),
        ]);

        $this->getJson('/api/bootstrap?page=relay-dashboard', [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertOk()
            ->assertJsonPath('app.page', 'relay-dashboard')
            ->assertJsonPath('security.sessionLifetimeMinutes', (int) config('session.lifetime'))
            ->assertJsonPath('settings.bootstrapUrl', '/api/bootstrap')
            ->assertJsonPath('settings.sessionPingUrl', '/api/session/ping');

        $this->getJson('/api/csrf-token', [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertOk()
            ->assertJsonStructure(['csrfToken']);

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'relaypass123',
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.account.email', $user->email)
            ->assertJsonPath('data.account.role', $user->role)
            ->assertJsonStructure([
                'status',
                'data' => ['account', 'csrf_token'],
                'meta',
                'error',
            ]);

        $this->getJson('/api/user', [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.authenticated', true)
            ->assertJsonPath('data.account.email', $user->email);

        $this->getJson('/api/session/ping', [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.session_lifetime_minutes', (int) config('session.lifetime'))
            ->assertJsonStructure([
                'status',
                'data' => ['csrf_token', 'touched_at', 'session_lifetime_minutes'],
                'meta',
                'error',
            ]);

        $this->postJson('/api/logout', [], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonStructure([
                'status',
                'data' => ['csrf_token'],
                'meta',
                'error',
            ]);

        $this->getJson('/api/user', [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.authenticated', false)
            ->assertJsonPath('data.account', null);

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertStatus(422)
            ->assertJsonPath('status', false)
            ->assertJsonPath('error.message', 'Invalid operator credentials.');
    }
}
