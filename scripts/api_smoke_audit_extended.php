<?php

/** Extended audit — append to debug-ca9706.log. Run: php scripts/api_smoke_audit_extended.php */

$logPath = dirname(__DIR__) . '/debug-ca9706.log';

function auditLog(string $hypothesisId, string $message, array $data = []): void
{
    global $logPath;
    file_put_contents($logPath, json_encode([
        'sessionId' => 'ca9706', 'hypothesisId' => $hypothesisId,
        'location' => 'api_smoke_audit_extended.php', 'message' => $message,
        'data' => $data, 'timestamp' => (int) (microtime(true) * 1000), 'runId' => 'audit-ext',
    ], JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND);
}

require dirname(__DIR__) . '/vendor/autoload.php';
$app = require_once dirname(__DIR__) . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$base = 'http://127.0.0.1:8000/api';

function login(string $base, string $email): ?string
{
    $res = \Illuminate\Support\Facades\Http::acceptJson()->post("{$base}/login", [
        'email' => $email, 'password' => 'password',
    ]);
    if (! $res->successful()) {
        return null;
    }
    return $res->json('data.token') ?? null;
}

$admin = login($base, 'admin@duniatex.com');
$qc = login($base, 'qc@duniatex.com');
$operator = login($base, 'operator@duniatex.com');

$clientId = 1;
$operatorId = \App\Models\User::where('role', 'operator')->value('id');
$qcId = \App\Models\User::where('role', 'qc')->value('id');
$machineId = \App\Models\Machine::value('id');

// H-BUG1: create request WITH qc_id
$r1 = \Illuminate\Support\Facades\Http::withToken($admin)->post("{$base}/admin/inspection-requests", [
    'client_id' => $clientId, 'operator_id' => $operatorId, 'qc_id' => $qcId,
    'machine_id' => $machineId, 'total_roll' => 1, 'opk' => 'OPK-EXT-' . time(), 'batch_number' => 'EXT-' . time(),
]);
auditLog('BUG1', 'create_request_with_qc_id', ['status' => $r1->status(), 'message' => $r1->json('message')]);

$rollId = $r1->json('data.fabric_rolls.0.id') ?? null;

if ($rollId && $r1->successful()) {
    $start = \Illuminate\Support\Facades\Http::withToken($operator)->post("{$base}/operator/rolls/{$rollId}/start", [
        'shift' => 'siang', 'weight_kg' => 40,
        'yarn_name' => 'EXT Test',
        'qc_name' => 'QC EXT Test',
        'batch_number' => 'LOT-EXT-123',
        'machine_name' => 'Machine EXT Test',
    ]);
    auditLog('BUG1', 'workflow_start', ['status' => $start->status()]);
    $inspId = $start->json('data.id');
    if ($inspId) {
        $dtId = \App\Models\DefectType::value('id');
        \Illuminate\Support\Facades\Http::withToken($operator)->post("{$base}/operator/inspections/{$inspId}/defects", [
            'defect_type_id' => $dtId, 'position_meter' => 5, 'point' => 1,
        ]);
        $fin = \Illuminate\Support\Facades\Http::withToken($operator)->post("{$base}/operator/inspections/{$inspId}/finish", [
            'length_meter' => 80,
            'gramasi' => '145-150',
            'lebar' => 180,
        ]);
        auditLog('BUG1', 'workflow_finish', ['status' => $fin->status(), 'score' => $fin->json('data.score')]);
        $val = \Illuminate\Support\Facades\Http::withToken($qc)->post("{$base}/qc/inspections/{$inspId}/validate");
        auditLog('BUG1', 'workflow_validate', ['status' => $val->status()]);
    }
}

// H-BUG2: defect category silently dropped
$cat = \Illuminate\Support\Facades\Http::withToken($admin)->post("{$base}/admin/defect-types", [
    'defect_name' => 'CatTest' . time(), 'category' => 'woven', 'default_point' => 2,
]);
$created = $cat->json('data');
auditLog('BUG2', 'defect_type_category', [
    'status' => $cat->status(),
    'sent_category' => 'woven',
    'returned_has_category' => isset($created['category']),
]);

// H-BUG3: FabricRoll hasOne — after reject, multiple inspections
$roll = \App\Models\FabricRoll::where('status', 'VALIDATED')->first();
if ($roll) {
    $inspCount = \App\Models\Inspection::where('roll_id', $roll->id)->count();
    $relInsp = $roll->inspection;
    auditLog('BUG3', 'hasOne_inspection_relation', [
        'roll_id' => $roll->id,
        'inspection_count' => $inspCount,
        'hasOne_returns_id' => $relInsp?->id,
        'note' => 'hasOne may return oldest inspection if count > 1',
    ]);
}

// H-BUG4: QC add roll without operator_id
$req = \App\Models\InspectionRequest::first();
if ($req) {
    $qcRoll = \Illuminate\Support\Facades\Http::withToken($qc)->post("{$base}/qc/requests/{$req->id}/rolls", [
        'roll_code' => 'QC-ROLL-' . time(),
        'machine_id' => $machineId,
        'batch_number' => 'B1',
    ]);
    auditLog('BUG4', 'qc_add_roll_without_operator', ['status' => $qcRoll->status(), 'message' => $qcRoll->json('message')]);
}

// H-BUG5: reassign on IN_PROGRESS should fail
$inProg = \App\Models\FabricRoll::where('status', 'IN_PROGRESS')->first();
if ($inProg) {
    $re = \Illuminate\Support\Facades\Http::withToken($admin)->patch("{$base}/admin/fabric-rolls/{$inProg->id}/reassign", [
        'operator_id' => $operatorId,
    ]);
    auditLog('BUG5', 'reassign_in_progress', ['status' => $re->status(), 'message' => $re->json('message')]);
}

// H-BUG6: admin export report
$exp = \Illuminate\Support\Facades\Http::withToken($admin)->get("{$base}/admin/reports/export");
auditLog('BUG6', 'admin_reports_export', ['status' => $exp->status(), 'content_type' => $exp->header('Content-Type')]);

echo "Extended audit written to debug-ca9706.log\n";
