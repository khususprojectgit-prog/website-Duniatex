<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Gramasi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GramasiCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $qc;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name'     => 'Admin Test',
            'email'    => 'admin@test.com',
            'password' => bcrypt('password'),
            'role'     => 'admin',
            'status'   => 'active',
        ]);

        $this->qc = User::create([
            'name'     => 'QC Test',
            'email'    => 'qc@test.com',
            'password' => bcrypt('password'),
            'role'     => 'qc',
            'status'   => 'active',
        ]);
    }

    public function test_admin_can_crud_gramasi(): void
    {
        Sanctum::actingAs($this->admin);

        // 1. Store a new gramasi
        $response = $this->postJson('/api/admin/gramasis', [
            'range'       => '140-145',
            'description' => 'Light fabric',
        ]);
        $response->assertStatus(201);
        $this->assertDatabaseHas('gramasis', ['range' => '140-145']);

        // 2. Validate range format (must be min-max)
        $responseErr = $this->postJson('/api/admin/gramasis', [
            'range' => 'invalid-range',
        ]);
        $responseErr->assertStatus(422);

        // 3. Index retrieved
        $responseGet = $this->getJson('/api/admin/gramasis');
        $responseGet->assertStatus(200);
        $this->assertCount(1, $responseGet->json('data'));

        // 4. Update
        $gramasi = Gramasi::firstOrFail();
        $responseUpdate = $this->putJson("/api/admin/gramasis/{$gramasi->id}", [
            'range'       => '145-150',
            'description' => 'Medium fabric',
        ]);
        $responseUpdate->assertStatus(200);
        $this->assertDatabaseHas('gramasis', ['range' => '145-150']);

        // 5. Delete
        $responseDelete = $this->deleteJson("/api/admin/gramasis/{$gramasi->id}");
        $responseDelete->assertStatus(200);
        $this->assertDatabaseMissing('gramasis', ['range' => '145-150']);
    }

    public function test_qc_can_read_gramasis_but_not_write(): void
    {
        // Add a test gramasi first
        $gramasi = Gramasi::create([
            'range'       => '150-155',
            'description' => 'Heavy fabric',
        ]);

        // QC Login
        Sanctum::actingAs($this->qc);

        // QC can fetch
        $responseGet = $this->getJson('/api/qc/gramasis');
        $responseGet->assertStatus(200);
        $this->assertCount(1, $responseGet->json('data'));

        // QC cannot create
        $responsePost = $this->postJson('/api/admin/gramasis', [
            'range' => '160-165',
        ]);
        $responsePost->assertStatus(403); // Forbidden
    }
}
