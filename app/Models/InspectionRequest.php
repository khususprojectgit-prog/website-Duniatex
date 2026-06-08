<?php

namespace App\Models;

use App\Enums\FabricRollStatus;
use App\Enums\InspectionRequestStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InspectionRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'yarn_type_id',
        'setting_id',
        'gramasi',
        'request_code',
        'opk',
        'notes',
        'qc_id',
        'status',
        'request_date',
    ];

    protected function casts(): array
    {
        return [
            'request_date' => 'date',
            'status'       => InspectionRequestStatus::class,
        ];
    }

    // -----------------------------------------------------------------------
    // State helpers
    // -----------------------------------------------------------------------

    /** All rolls have been validated — request is fully complete. */
    public function isComplete(): bool
    {
        return $this->status === InspectionRequestStatus::COMPLETED;
    }

    /** Request has reached its final state. */
    public function isFinal(): bool
    {
        return $this->status->isFinal();
    }

    // -----------------------------------------------------------------------
    // Scopes
    // -----------------------------------------------------------------------

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    // -----------------------------------------------------------------------
    // Relationships
    // -----------------------------------------------------------------------

    public function yarnType()
    {
        return $this->belongsTo(YarnType::class);
    }

    public function setting()
    {
        return $this->belongsTo(Setting::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function qc()
    {
        return $this->belongsTo(User::class, 'qc_id');
    }

    public function fabricRolls()
    {
        return $this->hasMany(FabricRoll::class, 'request_id');
    }

    // -----------------------------------------------------------------------
    // Utilities
    // -----------------------------------------------------------------------

    /** Auto-generate request_code. */
    public static function generateCode(): string
    {
        $count = self::count() + 1;
        return 'REQ-' . now()->format('Ymd') . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
    }
}
