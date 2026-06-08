<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\DefectType;
use App\Models\FabricRoll;
use App\Models\Inspection;
use App\Models\Machine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiFullAuditTest extends TestCase
{
    use RefreshDatabase;

    private string $logPath;

    private array $failures = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->logPath = base_path('debug-ca9706.log');
        $this->seedAuditUsers();
    }

    private function seedAuditUsers(): void
    {
        User::create(['name' => 'Admin', 'email' => 'admin@duniatex.com', 'password' => Hash::make('password'), 'role' => 'admin', 'status' => 'active']);
        User::create(['name' => 'QC', 'email' => 'qc@duniatex.com', 'password' => Hash::make('password'), 'role' => 'qc', 'status' => 'active']);
        User::create(['name' => 'Operator', 'email' => 'operator@duniatex.com', 'password' => Hash::make('password'), 'role' => 'qc', 'status' => 'active']);
        DefectType::create(['defect_name' => 'Hole', 'default_point' => 4, 'description' => 'test']);
        Client::create(['client_name' => 'Test Client', 'company' => 'PT Test']);
        Machine::create(['machine_name' => 'Loom T-01', 'machine_type' => 'Test', 'location' => 'Hall']);
    }

    private function auditLog(string $hypothesisId, string $location, string $message, array $data = []): void
    {
        $line = json_encode([
            'sessionId'    => 'ca9706',
            'hypothesisId' => $hypothesisId,
            'location'     => $location,
            'message'      => $message,
            'data'         => $data,
            'timestamp'    => (int) (microtime(true) * 1000),
            'runId'        => 'phpunit-audit',
        ], JSON_UNESCAPED_UNICODE);
        file_put_contents($this->logPath, $line . PHP_EOL, FILE_APPEND);
    }

    private function actingAsRole(string $role): User
    {
        $user = User::where('role', $role)->firstOrFail();
        Sanctum::actingAs($user);

        return $user;
    }

    private function assertEndpoint(string $name, string $method, string $uri, int $expectedStatus): void
    {
        $req = $this->withHeaders(['Accept' => 'application/json']);
        $response = match (strtoupper($method)) {
            'GET'    => $req->getJson($uri),
            'POST'   => $req->postJson($uri, []),
            'PATCH'  => $req->patchJson($uri, []),
            'DELETE' => $req->deleteJson($uri),
            default  => $req->getJson($uri),
        };

        $ok = $response->status() === $expectedStatus;
        $this->auditLog('ENDPOINT', 'ApiFullAuditTest.php', $ok ? 'PASS' : 'FAIL', [
            'endpoint' => $name,
            'status'   => $response->status(),
            'expected' => $expectedStatus,
            'message'  => $response->json('message'),
        ]);

        if (! $ok) {
            $this->failures[] = [
                'endpoint' => $name,
                'status'   => $response->status(),
                'expected' => $expectedStatus,
                'message'  => $response->json('message'),
            ];
        }
    }

    public function test_full_api_audit(): void
    {
        if (file_exists($this->logPath)) {
            unlink($this->logPath);
        }

        $this->actingAsRole('admin');
        $adminGets = [
            'admin/users', 'admin/clients', 'admin/machines', 'admin/defect-types',
            'admin/inspection-requests', 'admin/fabric-rolls',
            'admin/reports/summary', 'admin/reports/defect-analysis',
            'admin/reports/operator-performance', 'admin/reports/machine-issues',
            'admin/analytics/summary', 'admin/analytics/trends',
            'admin/analytics/defects', 'admin/analytics/machines', 'admin/analytics/inspections',
        ];
        foreach ($adminGets as $uri) {
            $this->assertEndpoint("GET /api/{$uri}", 'GET', "/api/{$uri}", 200);
        }

        $this->actingAsRole('qc');
        $qcGets = [
            'qc/requests',
            'qc/reports/summary', 'qc/reports/defect-analysis', 'qc/reports/machine-issues',
            'qc/machine-issues',
        ];
        foreach ($qcGets as $uri) {
            $this->assertEndpoint("GET /api/{$uri}", 'GET', "/api/{$uri}", 200);
        }

        $this->actingAsRole('qc');
        $opGets = ['qc/my-rolls', 'qc/my-inspections', 'qc/defect-types'];
        foreach ($opGets as $uri) {
            $this->assertEndpoint("GET /api/{$uri}", 'GET', "/api/{$uri}", 200);
        }

        $this->actingAsRole('admin');
        $this->assertEndpoint('admin GET operator/rolls', 'GET', '/api/qc/my-rolls', 403);

        // Workflow
        $clientId = Client::first()->id;
        $operatorId = User::where('role', 'qc')->first()->id;
        $machineId = Machine::first()->id;

        // BUG-1 fix: create without qc_id must succeed (QC optional in admin UI)
        $this->actingAsRole('admin');
        $createNoQc = $this->postJson('/api/admin/inspection-requests', [
            'client_id'   => $clientId,
            'opk'         => 'OPK-AUDIT-NOQC',
            'operator_id' => $operatorId,
            'machine_id'  => $machineId,
            'total_roll'  => 1,
            'batch_number'=> 'AUDIT-NOQC',
        ]);
        $this->auditLog('BUG1', 'ApiFullAuditTest.php', 'create_without_qc_id', [
            'status' => $createNoQc->status(),
        ]);
        if ($createNoQc->status() !== 201) {
            $this->failures[] = ['endpoint' => 'POST without qc_id', 'status' => $createNoQc->status(), 'message' => $createNoQc->json('message')];
        }

        $create = $this->postJson('/api/admin/inspection-requests', [
            'client_id'   => $clientId,
            'opk'         => 'OPK-AUDIT-QC',
            'operator_id' => $operatorId,
            'machine_id'  => $machineId,
            'total_roll'  => 1,
            'batch_number'=> 'AUDIT-001',
        ]);

        $this->auditLog('H4', 'ApiFullAuditTest.php', 'create_request', [
            'status'  => $create->status(),
            'message' => $create->json('message'),
        ]);

        if ($create->status() !== 201) {
            $this->failures[] = ['endpoint' => 'POST admin/inspection-requests', 'status' => $create->status(), 'message' => $create->json('message')];
        } else {
            $rollId = $create->json('data.fabric_rolls.0.id');
            $this->actingAsRole('qc');
            $start = $this->postJson("/api/qc/rolls/{$rollId}/start", [
                'shift' => 'pagi',
                'weight_kg' => 50,
                'yarn_name' => 'PE Audit',
                'qc_name' => 'QC Budi',
                'batch_number' => 'LOT-AUDIT',
                'machine_name' => 'Loom T-01',
            ]);

            $this->auditLog('H4', 'ApiFullAuditTest.php', 'start_inspection', [
                'status' => $start->status(),
                'message'=> $start->json('message'),
            ]);

            if ($start->status() === 201) {
                $inspId = $start->json('data.id');
                $dtId = DefectType::first()->id;
                $this->postJson("/api/qc/inspections/{$inspId}/defects", [
                    'defect_type_id' => $dtId, 'position_meter' => 10, 'point' => 2, 'side' => 'depan',
                ])->assertStatus(201)->assertJsonPath('data.side', 'depan');

                $this->postJson("/api/qc/inspections/{$inspId}/defects", [
                    'defect_type_id' => $dtId, 'position_meter' => 12, 'point' => 3, 'side' => 'belakang',
                ])->assertStatus(201)->assertJsonPath('data.side', 'belakang');

                $finish = $this->postJson("/api/qc/inspections/{$inspId}/finish", [
                    'length_meter' => 100,
                    'gramasi' => '145-150',
                    'lebar' => 180,
                    'weight_kg' => 50,
                    'machine_name' => 'Loom T-01',
                    'batch_number' => 'LOT-AUDIT',
                    'manual_roll_number' => 123,
                    'result' => 'A'
                ]);
                $this->auditLog('H4', 'ApiFullAuditTest.php', 'finish_inspection', ['status' => $finish->status()]);
                $finish->assertStatus(200);

                // Assert that the parent request has automatically transitioned to COMPLETED
                $reqId = $finish->json('data.roll.request_id');
                $this->actingAsRole('admin');
                $requestCheck = $this->getJson("/api/admin/inspection-requests");
                $requestCheck->assertOk();
                $matchingRequest = collect($requestCheck->json('data.data'))->firstWhere('id', $reqId);
                $this->assertEquals('COMPLETED', $matchingRequest['status']);

                $this->actingAsRole('qc');
                $this->getJson("/api/inspections/{$inspId}")->assertOk();
                $this->getJson("/api/qc/inspections/{$inspId}")->assertOk();
                $this->getJson("/api/inspections/{$inspId}/timeline")->assertOk();

                // Test rejection - should auto-revert request status to IN_PROGRESS
                $this->actingAsRole('admin');
                $reject = $this->postJson("/api/admin/inspections/{$inspId}/reject", [
                    'reason' => 'Rejecting for testing auto-revert',
                ]);
                $reject->assertStatus(200);

                // Assert request is back to IN_PROGRESS
                $this->actingAsRole('admin');
                $requestCheckAfterReject = $this->getJson("/api/admin/inspection-requests");
                $requestCheckAfterReject->assertOk();
                $matchingRequestAfterReject = collect($requestCheckAfterReject->json('data.data'))->firstWhere('id', $reqId);
                $this->assertEquals('IN_PROGRESS', $matchingRequestAfterReject['status']);
            } else {
                $this->failures[] = ['endpoint' => 'POST operator/start', 'status' => $start->status(), 'message' => $start->json('message')];
            }
        }

        // Defect type category not in DB — expect 422 if sent
        $this->actingAsRole('admin');
        $catRes = $this->postJson('/api/admin/defect-types', [
            'defect_name' => 'Test Cat ' . time(), 'category' => 'woven', 'default_point' => 2,
        ]);
        $created = $catRes->json('data');
        $this->auditLog('BUG2', 'ApiFullAuditTest.php', 'defect_category_field', [
            'status'          => $catRes->status(),
            'stored_category' => $created['category'] ?? null,
        ]);
        if (($created['category'] ?? null) !== 'woven') {
            $this->failures[] = ['endpoint' => 'defect category', 'status' => $catRes->status(), 'message' => 'category not stored'];
        }

        // Missing operator_id on create
        $bad = $this->postJson('/api/admin/inspection-requests', [
            'client_id' => $clientId, 'opk' => 'OPK-BAD', 'total_roll' => 1,
        ]);
        $this->auditLog('H5', 'ApiFullAuditTest.php', 'missing_operator_id', ['status' => $bad->status()]);

        // Reassign
        $roll = FabricRoll::first();
        if ($roll) {
            $reassign = $this->patchJson("/api/admin/fabric-rolls/{$roll->id}/reassign", [
                'operator_id' => $operatorId,
            ]);
            $this->auditLog('H6', 'ApiFullAuditTest.php', 'reassign', ['status' => $reassign->status()]);
        }

        $this->auditLog('SUMMARY', 'ApiFullAuditTest.php', 'audit_complete', [
            'failure_count' => count($this->failures),
            'failures'      => $this->failures,
        ]);

        if ($this->failures) {
            $this->fail('API audit failures: ' . json_encode($this->failures, JSON_UNESCAPED_UNICODE));
        }
    }

    public function test_start_inspection_with_existing_qc_email(): void
    {
        $clientId = Client::first()->id;
        $operator = User::where('role', 'qc')->firstOrFail();
        $machineId = Machine::first()->id;

        $this->actingAsRole('admin');
        $create = $this->postJson('/api/admin/inspection-requests', [
            'client_id'   => $clientId,
            'opk'         => 'OPK-EXISTING-QC',
            'operator_id' => $operator->id,
            'machine_id'  => $machineId,
            'total_roll'  => 1,
            'batch_number'=> 'AUDIT-EXISTING',
        ]);
        $create->assertStatus(201);
        $rollId = $create->json('data.fabric_rolls.0.id');

        // The seeded user is Name: 'QC', Email: 'qc@duniatex.com'
        // If we start inspection with qc_name: 'qc' (which generates 'qc@duniatex.com')
        // it must not crash due to duplicate entry.
        $this->actingAsRole('qc');
        $start = $this->postJson("/api/qc/rolls/{$rollId}/start", [
            'shift' => 'pagi',
            'weight_kg' => 45,
            'yarn_name' => 'PE Audit',
            'qc_name' => 'qc',
            'batch_number' => 'LOT-EXISTING',
            'machine_name' => 'Loom T-01',
        ]);

        $start->assertStatus(201);

        // Verify directly in the database that the inspection request's qc_id
        // was mapped to the existing QC user (qc@duniatex.com) without creating a new duplicate user.
        $requestCheck = \App\Models\InspectionRequest::where('opk', 'OPK-EXISTING-QC')->firstOrFail();
        $qcUser = User::where('email', 'qc@duniatex.com')->firstOrFail();
        
        $this->assertEquals($qcUser->id, $requestCheck->qc_id);
    }

    public function test_start_inspection_by_different_qc_user_succeeds_and_reassigns_operator(): void
    {
        $clientId = Client::first()->id;
        $machineId = Machine::first()->id;

        $qc1 = User::where('email', 'qc@duniatex.com')->firstOrFail();
        $qc2 = User::where('email', 'operator@duniatex.com')->firstOrFail();

        $this->actingAsRole('admin');
        $create = $this->postJson('/api/admin/inspection-requests', [
            'client_id'   => $clientId,
            'opk'         => 'OPK-DIFF-QC',
            'operator_id' => $qc1->id,
            'machine_id'  => $machineId,
            'total_roll'  => 1,
            'batch_number'=> 'AUDIT-DIFF',
        ]);
        $create->assertStatus(201);
        $rollId = $create->json('data.fabric_rolls.0.id');

        $rollBefore = FabricRoll::findOrFail($rollId);
        $this->assertEquals($qc1->id, $rollBefore->operator_id);

        Sanctum::actingAs($qc2);
        $start = $this->postJson("/api/qc/rolls/{$rollId}/start", [
            'shift' => 'siang',
            'weight_kg' => 60,
            'yarn_name' => 'PE Diff',
            'qc_name' => 'QC Dua',
            'batch_number' => 'LOT-DIFF',
            'machine_name' => 'Loom T-01',
        ]);

        $start->assertStatus(201);

        $rollAfter = FabricRoll::findOrFail($rollId);
        $this->assertEquals($qc2->id, $rollAfter->operator_id);
    }
}
