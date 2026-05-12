<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Log;

class AuditService
{
    /**
     * Record a lifecycle event in the audit log.
     *
     * Defensive by design: any internal exception is caught and forwarded to
     * the Laravel log, so an audit failure never aborts the parent business
     * transaction or returns an error to the caller.
     *
     * @param  string  $action       Lowercase snake_case event key.
     *                               e.g. 'inspection_started', 'request_completed'
     * @param  string  $description  Human-readable sentence describing the event.
     *                               MUST be readable without additional context.
     * @param  array   $context {
     *     @type int|null   $user_id        Authenticated actor (null for system events).
     *     @type int|null   $inspection_id  Related inspection, if any.
     *     @type int|null   $roll_id        Related fabric roll, if any.
     *     @type int|null   $request_id     Related inspection request, if any.
     *     @type array|null $metadata       Structured extra data (state transitions, reasons, …).
     * }
     */
    public function log(string $action, string $description, array $context = []): void
    {
        try {
            AuditLog::create([
                'user_id'       => $context['user_id']       ?? null,
                'inspection_id' => $context['inspection_id'] ?? null,
                'roll_id'       => $context['roll_id']       ?? null,
                'request_id'    => $context['request_id']    ?? null,
                'action'        => strtolower($action),
                'description'   => $description,
                'metadata'      => $context['metadata']      ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::error('AuditService: failed to write audit log entry', [
                'action'      => $action,
                'description' => $description,
                'context'     => $context,
                'error'       => $e->getMessage(),
                'trace'       => $e->getTraceAsString(),
            ]);
        }
    }
}
