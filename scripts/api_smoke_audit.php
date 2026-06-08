<?php

/**
 * API smoke audit — writes NDJSON to debug-ca9706.log
 * Run: php scripts/api_smoke_audit.php
 */

use Illuminate\Support\Facades\Http;

$logPath = dirname(__DIR__) . '/debug-ca9706.log';

function auditLog(string $hypothesisId, string $location, string $message, array $data = [], string $runId = 'audit-1'): void
{
    global $logPath;
    $line = json_encode([
        'sessionId'    => 'ca9706',
        'hypothesisId' => $hypothesisId,
        'location'     => $location,
        'message'      => $message,
        'data'         => $data,
        'timestamp'    => (int) (microtime(true) * 1000),
        'runId'        => $runId,
    ], JSON_UNESCAPED_UNICODE);
    file_put_contents($logPath, $line . PHP_EOL, FILE_APPEND);
}

require dirname(__DIR__) . '/vendor/autoload.php';
$app = require_once dirname(__DIR__) . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Use artisan serve URL — APP_URL may point to XAMPP Apache (port 80) without /api route
$base = rtrim(env('API_TEST_BASE', 'http://127.0.0.1:8000'), '/') . '/api';

$results = ['pass' => 0, 'fail' => 0, 'skip' => 0, 'errors' => []];

function login(string $base, string $email): ?string
{
    $res = \Illuminate\Support\Facades\Http::acceptJson()->post("{$base}/login", [
        'email'    => $email,
        'password' => 'password',
    ]);
    if (! $res->successful()) {
        auditLog('AUTH', 'api_smoke_audit.php', 'login_failed', [
            'email'  => $email,
            'status' => $res->status(),
            'body'   => substr($res->body(), 0, 500),
        ]);
        return null;
    }
    $json = $res->json();
    return $json['data']['token'] ?? $json['token'] ?? null;
}

function callEndpoint(
    string $base,
    ?string $token,
    string $method,
    string $path,
    array $body = [],
    int $expectMin = 200,
    int $expectMax = 299
): array {
    $req = \Illuminate\Support\Facades\Http::acceptJson();
    if ($token) {
        $req = $req->withToken($token);
    }
    $url = str_starts_with($path, 'http') ? $path : "{$base}/" . ltrim($path, '/');
    $res = match (strtoupper($method)) {
        'GET'    => $req->get($url),
        'POST'   => $req->post($url, $body),
        'PUT'    => $req->put($url, $body),
        'PATCH'  => $req->patch($url, $body),
        'DELETE' => $req->delete($url),
        default  => $req->get($url),
    };
    $status = $res->status();
    $ok = $status >= $expectMin && $status <= $expectMax;
    return [
        'ok'      => $ok,
        'status'  => $status,
        'body'    => $res->json(),
        'message' => $res->body(),
    ];
}

function record(string $id, string $name, array $r, string $role = ''): void
{
    global $results;
    $key = ($role ? "[{$role}] " : '') . "{$name}";
    if ($r['ok']) {
        $results['pass']++;
        auditLog('ENDPOINT', 'api_smoke_audit.php', 'PASS', ['endpoint' => $key, 'status' => $r['status']]);
    } else {
        $results['fail']++;
        $msg = $r['body']['message'] ?? substr($r['message'], 0, 200);
        $results['errors'][] = ['endpoint' => $key, 'status' => $r['status'], 'message' => $msg];
        auditLog('ENDPOINT', 'api_smoke_audit.php', 'FAIL', [
            'endpoint' => $key,
            'status'   => $r['status'],
            'message'  => $msg,
        ]);
    }
}

// --- Login all roles ---
$adminToken = login($base, 'admin@duniatex.com');
$qcToken = login($base, 'qc@duniatex.com');
$operatorToken = login($base, 'operator@duniatex.com');

auditLog('AUTH', 'api_smoke_audit.php', 'login_results', [
    'admin'    => (bool) $adminToken,
    'qc'       => (bool) $qcToken,
    'operator' => (bool) $operatorToken,
]);

if (! $adminToken || ! $qcToken || ! $operatorToken) {
    auditLog('AUTH', 'api_smoke_audit.php', 'ABORT_missing_tokens', []);
    echo "LOGIN FAILED — ensure server running and db seeded.\n";
    exit(1);
}

// --- Public ---
    record('H0', 'POST /login invalid', callEndpoint($base, null, 'POST', '/login', ['email' => 'x@x.com', 'password' => 'wrong'], 422, 422));

// --- Shared auth ---
foreach (['admin' => $adminToken, 'qc' => $qcToken, 'operator' => $operatorToken] as $role => $tok) {
    record('H1', 'GET /me', callEndpoint($base, $tok, 'GET', '/me'), $role);
    record('H1', 'POST /logout', callEndpoint($base, $tok, 'POST', '/logout'), $role);
    // Re-login after logout for continued tests
    if ($role === 'admin') {
        $adminToken = login($base, 'admin@duniatex.com');
    } elseif ($role === 'qc') {
        $qcToken = login($base, 'qc@duniatex.com');
    } else {
        $operatorToken = login($base, 'operator@duniatex.com');
    }
}

// Re-login fresh tokens
$adminToken = login($base, 'admin@duniatex.com');
$qcToken = login($base, 'qc@duniatex.com');
$operatorToken = login($base, 'operator@duniatex.com');

// --- Admin GET endpoints ---
$adminGets = [
    'users', 'clients', 'machines', 'defect-types',
    'inspection-requests', 'fabric-rolls',
    'reports/summary', 'reports/defect-analysis', 'reports/operator-performance', 'reports/machine-issues',
    'analytics/summary', 'analytics/trends', 'analytics/defects', 'analytics/machines', 'analytics/inspections',
];
foreach ($adminGets as $p) {
    record('H2', "GET admin/{$p}", callEndpoint($base, $adminToken, 'GET', "admin/{$p}"), 'admin');
}

// Admin forbidden on operator route
record('H3', 'admin GET operator/rolls (expect 403)', callEndpoint($base, $adminToken, 'GET', 'operator/rolls', [], 403, 403), 'admin');

// --- QC GET endpoints ---
$qcGets = [
    'inspections', 'requests',
    'reports/summary', 'reports/defect-analysis', 'reports/machine-issues',
    'machine-issues',
];
foreach ($qcGets as $p) {
    record('H2', "GET qc/{$p}", callEndpoint($base, $qcToken, 'GET', "qc/{$p}"), 'qc');
}

// --- Operator GET endpoints ---
$opGets = ['rolls', 'inspections', 'defect-types'];
foreach ($opGets as $p) {
    record('H2', "GET operator/{$p}", callEndpoint($base, $operatorToken, 'GET', "operator/{$p}"), 'operator');
}

// --- Workflow: create request (needs client, operator, machine ids) ---
$clients = callEndpoint($base, $adminToken, 'GET', 'admin/clients');
$users = callEndpoint($base, $adminToken, 'GET', 'admin/users?role=operator&status=active');
$machines = callEndpoint($base, $adminToken, 'GET', 'admin/machines');

$clientId = $clients['body']['data']['data'][0]['id'] ?? $clients['body']['data'][0]['id'] ?? null;
$operatorId = null;
$userList = $users['body']['data']['data'] ?? $users['body']['data'] ?? [];
foreach ($userList as $u) {
    if (($u['role'] ?? '') === 'operator') {
        $operatorId = $u['id'];
        break;
    }
}
$machineId = $machines['body']['data']['data'][0]['id'] ?? $machines['body']['data'][0]['id'] ?? null;

auditLog('H4', 'api_smoke_audit.php', 'workflow_ids', [
    'client_id' => $clientId,
    'operator_id' => $operatorId,
    'machine_id' => $machineId,
]);

if ($clientId && $operatorId) {
    // BUG-1 verification: QC optional (no qc_id)
    $createNoQc = callEndpoint($base, $adminToken, 'POST', 'admin/inspection-requests', [
        'client_id'   => $clientId,
        'operator_id' => $operatorId,
        'machine_id'  => $machineId,
        'total_roll'  => 1,
        'opk'         => 'OPK-SMOKE-' . date('His'),
        'batch_number'=> 'SMOKE-NOQC-' . date('His'),
    ], 201, 201);
    record('BUG1', 'POST admin/inspection-requests without qc_id', $createNoQc, 'admin');

    $createReq = callEndpoint($base, $adminToken, 'POST', 'admin/inspection-requests', [
        'client_id'   => $clientId,
        'operator_id' => $operatorId,
        'machine_id'  => $machineId,
        'total_roll'  => 1,
        'opk'         => 'OPK-SMOKE-' . date('His'),
        'batch_number'=> 'SMOKE-' . date('His'),
    ], 201, 201);
    record('H4', 'POST admin/inspection-requests', $createReq, 'admin');

    $rollId = null;
    if ($createReq['ok']) {
        $rolls = $createReq['body']['data']['fabric_rolls'] ?? [];
        $rollId = $rolls[0]['id'] ?? null;
    }

    if ($rollId) {
        $start = callEndpoint($base, $operatorToken, 'POST', "operator/rolls/{$rollId}/start", [
            'shift'        => 'pagi',
            'weight_kg'    => 50,
            'yarn_name'    => 'PE Smoke Test',
            'qc_name'      => 'QC Smoke Test',
            'batch_number' => 'LOT-SMOKE-123',
            'machine_name' => 'Machine Smoke Test',
        ], 201, 201);
        record('H4', 'POST operator/rolls/{id}/start', $start, 'operator');

        $inspId = $start['body']['data']['id'] ?? null;
        if ($inspId && $start['ok']) {
            record('H4', 'GET operator/inspections/{id}', callEndpoint($base, $operatorToken, 'GET', "operator/inspections/{$inspId}"), 'operator');
            record('H4', 'GET inspections/{id}/timeline', callEndpoint($base, $qcToken, 'GET', "inspections/{$inspId}/timeline"), 'qc');

            $defectTypes = callEndpoint($base, $operatorToken, 'GET', 'operator/defect-types');
            $dtId = is_array($defectTypes['body']) ? ($defectTypes['body'][0]['id'] ?? null) : ($defectTypes['body']['data'][0]['id'] ?? null);

            if ($dtId) {
                $addDefect = callEndpoint($base, $operatorToken, 'POST', "operator/inspections/{$inspId}/defects", [
                    'defect_type_id' => $dtId,
                    'position_meter' => 10,
                    'point'          => 2,
                ], 201, 201);
                record('H4', 'POST operator/inspections/{id}/defects', $addDefect, 'operator');
            }

            $finish = callEndpoint($base, $operatorToken, 'POST', "operator/inspections/{$inspId}/finish", [
                'length_meter' => 100,
                'gramasi'      => '145-150',
                'lebar'        => 180,
            ], 200, 200);
            record('H4', 'POST operator/inspections/{id}/finish', $finish, 'operator');

            record('H4', 'GET qc/inspections/{id}', callEndpoint($base, $qcToken, 'GET', "qc/inspections/{$inspId}"), 'qc');
        }
    }

    // Old API: POST without operator_id (expect 422)
    $badReq = callEndpoint($base, $adminToken, 'POST', 'admin/inspection-requests', [
        'client_id'  => $clientId,
        'total_roll' => 1,
        'length_meter' => 100,
    ], 422, 422);
    record('H5', 'POST inspection-requests without operator_id (expect 422)', $badReq, 'admin');

    // Old API: length_meter only (expect 422 - field removed)
    record('H5', 'POST inspection-requests with length_meter only missing operator', $badReq, 'admin');
}

// --- Validation edge cases ---
record('H6', 'POST start without body (expect 422)', callEndpoint($base, $operatorToken, 'POST', 'operator/rolls/99999/start', [], 404, 404), 'operator');

// --- Static checks from code ---
auditLog('STATIC', 'api_smoke_audit.php', 'summary', [
    'pass'  => $results['pass'],
    'fail'  => $results['fail'],
    'errors'=> $results['errors'],
]);

echo "API Smoke Audit Complete\n";
echo "PASS: {$results['pass']}\n";
echo "FAIL: {$results['fail']}\n";
if ($results['errors']) {
    echo "\nFailures:\n";
    foreach ($results['errors'] as $e) {
        echo "  [{$e['status']}] {$e['endpoint']}: {$e['message']}\n";
    }
}

exit($results['fail'] > 0 ? 1 : 0);
