$ErrorActionPreference = "SilentlyContinue"
$baseUrl = "http://127.0.0.1:8000"
$jsonHeaders = @{ "Content-Type" = "application/json"; "Accept" = "application/json" }
$pass = 0; $fail = 0

function Test-Step($label, $block) {
    Write-Host "`n[$label]" -ForegroundColor Yellow
    try {
        & $block
        $script:pass++
    } catch {
        Write-Host "  FAIL: $_" -ForegroundColor Red
        $script:fail++
    }
}

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  DUNIATEX API SMOKE TEST (v2 envelope)" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan

# 1. Health
Test-Step "1  GET /up" {
    $r = Invoke-WebRequest "$baseUrl/up" -UseBasicParsing
    Write-Host "  Status: $($r.StatusCode)  PASS" -ForegroundColor Green
}

# 2. GET /api/login => 405
Test-Step "2  GET /api/login (expect 405)" {
    $r = Invoke-WebRequest "$baseUrl/api/login" -UseBasicParsing -ErrorAction Stop 2>&1
    Write-Host "  Status: $($r.StatusCode)" -ForegroundColor Green
}

# 3. /api/me without token => 401
Test-Step "3  GET /api/me no token (expect 401)" {
    $r = Invoke-WebRequest "$baseUrl/api/me" -UseBasicParsing -Headers @{ "Accept" = "application/json" }
    Write-Host "  Status: $($r.StatusCode)" -ForegroundColor Green
}

# 4. Admin route without token => 401
Test-Step "4  GET /api/admin/users no token (expect 401)" {
    $r = Invoke-WebRequest "$baseUrl/api/admin/users" -UseBasicParsing -Headers @{ "Accept" = "application/json" }
    Write-Host "  Status: $($r.StatusCode)" -ForegroundColor Green
}

# 5. Login as admin — data.token, data.user.role
Test-Step "5  POST /api/login as admin" {
    $body = '{"email":"admin@duniatex.com","password":"password"}'
    $script:adminResp = Invoke-RestMethod "$baseUrl/api/login" -Method POST -Body $body -Headers $jsonHeaders
    $script:adminToken = $script:adminResp.data.token
    $script:adminHeader = @{ "Authorization" = "Bearer $($script:adminToken)"; "Accept" = "application/json" }
    Write-Host "  success: $($script:adminResp.success)"  -ForegroundColor Green
    Write-Host "  Token: $($script:adminToken.Substring(0,20))...  Role: $($script:adminResp.data.user.role)  PASS" -ForegroundColor Green
}

# 6. GET /api/me with admin token — data.name, data.role
Test-Step "6  GET /api/me (admin token)" {
    $me = Invoke-RestMethod "$baseUrl/api/me" -Headers $script:adminHeader
    Write-Host "  success: $($me.success)  Name: $($me.data.name)  Role: $($me.data.role)  PASS" -ForegroundColor Green
}

# 7. GET /api/admin/users — data.total
Test-Step "7  GET /api/admin/users" {
    $users = Invoke-RestMethod "$baseUrl/api/admin/users" -Headers $script:adminHeader
    Write-Host "  success: $($users.success)  Total users: $($users.data.total)  PASS" -ForegroundColor Green
}

# 8. GET /api/admin/defect-types — data is array
Test-Step "8  GET /api/admin/defect-types" {
    $dt = Invoke-RestMethod "$baseUrl/api/admin/defect-types" -Headers $script:adminHeader
    Write-Host "  success: $($dt.success)  Defect types: $($dt.data.Count)  PASS" -ForegroundColor Green
}

# 9. GET /api/admin/machines
Test-Step "9  GET /api/admin/machines" {
    $m = Invoke-RestMethod "$baseUrl/api/admin/machines" -Headers $script:adminHeader
    Write-Host "  success: $($m.success)  Machines: $($m.data.total)  PASS" -ForegroundColor Green
}

# 10. GET /api/admin/clients
Test-Step "10 GET /api/admin/clients" {
    $c = Invoke-RestMethod "$baseUrl/api/admin/clients" -Headers $script:adminHeader
    Write-Host "  success: $($c.success)  Clients: $($c.data.total)  PASS" -ForegroundColor Green
}

# 11. Admin tries operator route => 403
Test-Step "11 GET /api/operator/rolls as admin (expect 403)" {
    $r = Invoke-WebRequest "$baseUrl/api/operator/rolls" -Headers $script:adminHeader -UseBasicParsing
    Write-Host "  Status: $($r.StatusCode)" -ForegroundColor Green
}

# 12. Login as QC
Test-Step "12 POST /api/login as qc" {
    $body = '{"email":"qc@duniatex.com","password":"password"}'
    $script:qcResp = Invoke-RestMethod "$baseUrl/api/login" -Method POST -Body $body -Headers $jsonHeaders
    $script:qcHeader = @{ "Authorization" = "Bearer $($script:qcResp.data.token)"; "Accept" = "application/json" }
    Write-Host "  success: $($script:qcResp.success)  QC: $($script:qcResp.data.user.name)  PASS" -ForegroundColor Green
}

# 13. QC: list inspection requests
Test-Step "13 GET /api/qc/requests (qc token)" {
    $reqs = Invoke-RestMethod "$baseUrl/api/qc/requests" -Headers $script:qcHeader
    Write-Host "  success: $($reqs.success)  Total: $($reqs.data.total)  PASS" -ForegroundColor Green
}

# 14. QC tries admin route => 403
Test-Step "14 GET /api/admin/users as qc (expect 403)" {
    $r = Invoke-WebRequest "$baseUrl/api/admin/users" -Headers $script:qcHeader -UseBasicParsing
    Write-Host "  Status: $($r.StatusCode)" -ForegroundColor Green
}

# 15. Login as operator
Test-Step "15 POST /api/login as operator" {
    $body = '{"email":"operator@duniatex.com","password":"password"}'
    $script:opResp = Invoke-RestMethod "$baseUrl/api/login" -Method POST -Body $body -Headers $jsonHeaders
    $script:opHeader = @{ "Authorization" = "Bearer $($script:opResp.data.token)"; "Accept" = "application/json" }
    Write-Host "  success: $($script:opResp.success)  Operator: $($script:opResp.data.user.name)  PASS" -ForegroundColor Green
}

# 16. Operator: GET available rolls
Test-Step "16 GET /api/operator/rolls (operator token)" {
    $rolls = Invoke-RestMethod "$baseUrl/api/operator/rolls" -Headers $script:opHeader
    Write-Host "  success: $($rolls.success)  Available rolls: $($rolls.data.total)  PASS" -ForegroundColor Green
}

# 17. Operator: GET defect types
Test-Step "17 GET /api/operator/defect-types" {
    $dt = Invoke-RestMethod "$baseUrl/api/operator/defect-types" -Headers $script:opHeader
    Write-Host "  success: $($dt.success)  Defect types: $($dt.data.Count)  PASS" -ForegroundColor Green
}

# 18. Admin reports summary
Test-Step "18 GET /api/admin/reports/summary" {
    $s = Invoke-RestMethod "$baseUrl/api/admin/reports/summary" -Headers $script:adminHeader
    Write-Host "  success: $($s.success)  Total: $($s.data.total_inspections)  Pass rate: $($s.data.pass_rate)%  PASS" -ForegroundColor Green
}

# 19. Logout operator
Test-Step "19 POST /api/logout (operator)" {
    $r = Invoke-RestMethod "$baseUrl/api/logout" -Method POST -Headers $script:opHeader
    Write-Host "  success: $($r.success)  $($r.message)  PASS" -ForegroundColor Green
}

# 20. Operator uses revoked token => 401
Test-Step "20 GET /api/operator/rolls with revoked token (expect 401)" {
    $r = Invoke-WebRequest "$baseUrl/api/operator/rolls" -Headers $script:opHeader -UseBasicParsing
    Write-Host "  Status: $($r.StatusCode)" -ForegroundColor Green
}

Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "  RESULTS: $pass PASSED   $fail FAILED" -ForegroundColor $(if ($fail -eq 0) { "Green" } else { "Red" })
Write-Host "========================================" -ForegroundColor Cyan
