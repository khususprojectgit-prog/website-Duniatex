<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\Admin;
use App\Http\Controllers\QC;
use App\Http\Controllers\Operator;
use App\Http\Controllers\TimelineController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Duniatex Textile Inspection System
|--------------------------------------------------------------------------
| All routes are prefixed with /api automatically by Laravel 11.
| Authentication: Laravel Sanctum (stateless token).
| Authorization:  RoleMiddleware ('role' alias).
|--------------------------------------------------------------------------
*/

// -----------------------------------------------------------------------
// Public: Authentication
// -----------------------------------------------------------------------
Route::post('/login', [AuthController::class, 'login']);

// -----------------------------------------------------------------------
// Authenticated (any role)
// -----------------------------------------------------------------------
Route::middleware('auth:sanctum')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me',      [AuthController::class, 'me']);

    // -------------------------------------------------------------------
    // Admin routes
    // -------------------------------------------------------------------
    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {

        // Users
        Route::apiResource('users', Admin\UserController::class);

        // Clients
        Route::apiResource('clients', Admin\ClientController::class);

        // Machines
        Route::apiResource('machines', Admin\MachineController::class);

        // Defect types
        Route::apiResource('defect-types', Admin\DefectTypeController::class);

        // Reports
        Route::prefix('reports')->name('reports.')->group(function () {
            Route::get('summary',              [Admin\ReportController::class, 'summary']);
            Route::get('defect-analysis',      [Admin\ReportController::class, 'defectAnalysis']);
            Route::get('operator-performance', [Admin\ReportController::class, 'operatorPerformance']);
            Route::get('machine-issues',       [Admin\ReportController::class, 'machineIssues']);
            Route::get('export',               [Admin\ReportController::class, 'export']);
        });

        // Inspection Requests (admin orchestration — creates + lists with auto roll generation)
        Route::get( 'inspection-requests', [Admin\InspectionRequestController::class, 'index']);
        Route::post('inspection-requests', [Admin\InspectionRequestController::class, 'store']);

        // Fabric Rolls (admin monitoring — read only)
        Route::get('fabric-rolls', [Admin\FabricRollController::class, 'index']);

        // Analytics (read-only aggregations)
        Route::prefix('analytics')->name('analytics.')->group(function () {
            Route::get('summary',     [Admin\AnalyticsController::class, 'summary']);
            Route::get('trends',      [Admin\AnalyticsController::class, 'trends']);
            Route::get('defects',     [Admin\AnalyticsController::class, 'defects']);
            Route::get('machines',    [Admin\AnalyticsController::class, 'machines']);
            Route::get('inspections', [Admin\AnalyticsController::class, 'inspections']);
            Route::get('export/csv',  [Admin\AnalyticsController::class, 'exportCsv']);
        });
    });

    // -------------------------------------------------------------------
    // QC routes
    // -------------------------------------------------------------------
    Route::middleware('role:qc,admin')->prefix('qc')->name('qc.')->group(function () {

        // Inspection requests
        Route::apiResource('requests', QC\InspectionRequestController::class);

        // Fabric rolls nested under requests (list + add)
        Route::get( 'requests/{inspectionRequest}/rolls', [QC\FabricRollController::class, 'index']);
        Route::post('requests/{inspectionRequest}/rolls', [QC\FabricRollController::class, 'store']);

        // Standalone fabric roll operations (show, update, delete)
        Route::get(   'rolls/{fabricRoll}', [QC\FabricRollController::class, 'show']);
        Route::put(   'rolls/{fabricRoll}', [QC\FabricRollController::class, 'update']);
        Route::delete('rolls/{fabricRoll}', [QC\FabricRollController::class, 'destroy']);

        // Inspection validation
        Route::get('inspections',                             [QC\ValidationController::class, 'index']);
        Route::get('inspections/{inspection}',                [QC\ValidationController::class, 'show']);
        Route::post('inspections/{inspection}/validate',      [QC\ValidationController::class, 'validate']);
        Route::post('inspections/{inspection}/reject',        [QC\ValidationController::class, 'reject']);

        // Machine issues
        Route::apiResource('machine-issues', QC\MachineIssueController::class);

        // QC reports
        Route::prefix('reports')->name('reports.')->group(function () {
            Route::get('summary',         [QC\QCReportController::class, 'summary']);
            Route::get('defect-analysis', [QC\QCReportController::class, 'defectAnalysis']);
            Route::get('machine-issues',  [QC\QCReportController::class, 'machineIssues']);
        });
    });

    // -------------------------------------------------------------------
    // Timeline — accessible to any authenticated role
    // Authorization (operator = own inspection only) handled in controller
    // -------------------------------------------------------------------
    Route::get('/inspections/{inspection}/timeline', [TimelineController::class, 'show'])
         ->name('inspections.timeline');

    // -------------------------------------------------------------------
    // Operator routes
    // -------------------------------------------------------------------
    Route::middleware('role:operator')->prefix('operator')->name('operator.')->group(function () {

        // Available rolls to inspect
        Route::get('rolls',                    [Operator\OperatorInspectionController::class, 'availableRolls']);
        Route::post('rolls/{fabricRoll}/start', [Operator\OperatorInspectionController::class, 'start']);

        // Own inspections
        Route::get('inspections',                           [Operator\OperatorInspectionController::class, 'myInspections']);
        Route::get('inspections/{inspection}',              [Operator\OperatorInspectionController::class, 'show']);
        Route::post('inspections/{inspection}/finish',      [Operator\OperatorInspectionController::class, 'finish']);

        // Defects on an active inspection
        Route::get( 'inspections/{inspection}/defects', [Operator\DefectController::class, 'index']);
        Route::post('inspections/{inspection}/defects', [Operator\DefectController::class, 'store']);

        // Delete a single defect
        Route::delete('defects/{defect}',                   [Operator\DefectController::class, 'destroy']);

        // Defect type list for UI dropdown
        Route::get('defect-types',                          [Operator\DefectController::class, 'defectTypes']);
    });
});
