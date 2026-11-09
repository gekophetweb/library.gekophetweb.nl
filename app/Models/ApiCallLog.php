<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One call to an external catalogue / series API (guidelines §4.3).
 *
 * @property string $provider
 * @property string $operation
 * @property string $query
 * @property string $outcome success | not_found | failed | error
 */
class ApiCallLog extends Model
{
    public const string OUTCOME_SUCCESS = 'success';

    public const string OUTCOME_NOT_FOUND = 'not_found';

    public const string OUTCOME_FAILED = 'failed';

    public const string OUTCOME_ERROR = 'error';

    protected $fillable = [
        'user_id',
        'provider',
        'operation',
        'query',
        'category',
        'language',
        'outcome',
        'status_code',
        'duration_ms',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'status_code' => 'integer',
            'duration_ms' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
