<?php

namespace Tests\Feature;

use App\Models\Device;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DeviceCredentialLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_created_plaintext_credential_is_ephemeral_and_can_only_be_pulled_once(): void
    {
        // An undeclared Model property becomes an Eloquent attribute and may be persisted.
        $device = Device::factory()->create();

        $plaintextKey = $device->pullPlaintextApiKey();

        $this->assertNotNull($plaintextKey);
        $this->assertSame(64, strlen($plaintextKey));
        $this->assertNull($device->pullPlaintextApiKey());
        $this->assertNotContains($plaintextKey, $device->getAttributes());

        $stored = (array) DB::table('devices')->find($device->id);
        $this->assertNotContains($plaintextKey, $stored);
        $this->assertSame(hash('sha256', $plaintextKey), $stored['api_key_hash']);
    }

    public function test_rotated_plaintext_credential_is_not_an_eloquent_attribute_or_database_value(): void
    {
        $device = Device::factory()->create();
        $device->pullPlaintextApiKey();

        $rotatedKey = $device->rotateApiKey();

        $this->assertNotContains($rotatedKey, $device->getAttributes());

        $stored = (array) DB::table('devices')->find($device->id);
        $this->assertNotContains($rotatedKey, $stored);
        $this->assertSame(hash('sha256', $rotatedKey), $stored['api_key_hash']);
    }
}
