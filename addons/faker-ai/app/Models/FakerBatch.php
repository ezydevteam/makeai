<?php

namespace Addons\FakerAi\Models;

use App\Models\Admin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FakerBatch extends Model
{
    protected $table = 'faker_ai_batches';

    protected $fillable = [
        'type', 'label', 'target', 'target_label',
        'requested_count', 'inserted_count',
        'options', 'tokens_input', 'tokens_output',
        'status', 'error', 'created_by',
    ];

    protected $casts = [
        'options' => 'array',
        'requested_count' => 'integer',
        'inserted_count' => 'integer',
        'tokens_input' => 'integer',
        'tokens_output' => 'integer',
    ];

    /**
     * The targets this batch applies to, always as a list.
     *
     * `target` holds a JSON array of slugs/ulids, or the sentinel '*' meaning "everything".
     * Batches created before multi-select stored a bare string ('*' or one slug), so a
     * non-JSON value is read as a single-element list rather than being discarded.
     *
     * @return array<int,string>
     */
    public function targetList(): array
    {
        $raw = trim((string) $this->target);

        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return array_values(array_filter(array_map('strval', $decoded), fn ($v) => $v !== ''));
        }

        return [$raw];
    }

    /** Does this batch target everything of its type, rather than a chosen subset? */
    public function targetsAll(): bool
    {
        $targets = $this->targetList();

        return $targets === [] || in_array('*', $targets, true);
    }

    public function records(): HasMany
    {
        return $this->hasMany(FakerRecord::class, 'batch_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    /**
     * Log an inserted row so it can be deleted on rollback.
     */
    public function recordInsert(Model $model): FakerRecord
    {
        return $this->records()->create([
            'action' => 'delete',
            'subject_type' => $model::class,
            'subject_id' => (string) $model->getKey(),
        ]);
    }

    /**
     * Log a counter bump so exactly `$amount` can be subtracted back on rollback.
     */
    public function recordIncrement(Model $model, string $column, int $amount): FakerRecord
    {
        return $this->records()->create([
            'action' => 'decrement',
            'subject_type' => $model::class,
            'subject_id' => (string) $model->getKey(),
            'column' => $column,
            'amount' => $amount,
        ]);
    }
}
