<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use App\Models\Traits\HasCompanyId;

class ActivityLog extends Model
{
    use HasFactory, HasCompanyId;


    protected $fillable = [
        'log_type',
        'action',
        'description',
        'subject_type',
        'subject_id',
        'properties',
        'user_id',
        'company_id',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'properties' => 'array',
    ];

    /**
     * Get the user that performed the activity.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the company.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the subject of the activity.
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Log an activity.
     */
    public static function log(
        string $logType,
        string $action,
        string $description,
        ?Model $subject = null,
        ?array $properties = null
    ): self {
        $user = auth()->user();

        return self::create([
            'log_type' => $logType,
            'action' => $action,
            'description' => $description,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id' => $subject?->id,
            'properties' => $properties,
            'user_id' => $user?->id,
            'company_id' => $user?->company_id,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    /**
     * Scope to filter by type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('log_type', $type);
    }

    /**
     * Scope to filter by action.
     */
    public function scopeOfAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Scope to filter by date range.
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Scope to filter by user.
     */
    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Get the icon for the log type.
     */
    public function getIconAttribute(): string
    {
        $icons = [
            'auth' => 'bi-person-check',
            'invoice' => 'bi-receipt',
            'product' => 'bi-box',
            'stock' => 'bi-boxes',
            'customer' => 'bi-people',
            'payment' => 'bi-cash',
            'expense' => 'bi-wallet2',
            'user' => 'bi-person',
            'warehouse' => 'bi-building',
            'order' => 'bi-cart',
            'system' => 'bi-gear',
        ];

        return $icons[$this->log_type] ?? 'bi-activity';
    }

    /**
     * Get the color for the action.
     */
    public function getColorAttribute(): string
    {
        $colors = [
            'created' => 'success',
            'updated' => 'info',
            'deleted' => 'danger',
            'login' => 'primary',
            'logout' => 'secondary',
            'viewed' => 'light',
            'approved' => 'success',
            'cancelled' => 'warning',
            'paid' => 'success',
        ];

        return $colors[$this->action] ?? 'secondary';
    }
}
