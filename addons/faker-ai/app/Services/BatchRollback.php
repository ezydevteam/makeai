<?php

namespace Addons\FakerAi\Services;

use Addons\FakerAi\Models\FakerBatch;
use Addons\FakerAi\Models\FakerRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/**
 * Replays a batch's ledger in reverse — the single source of truth for undoing generated data,
 * shared by every generator's rollback() and used directly as a fallback when a generator is no
 * longer available (e.g. its addon was deactivated after the data was seeded).
 *
 *   - 'delete'    rows are removed (force-deleted past soft-delete, so fakes truly vanish).
 *   - 'decrement' counters have exactly the applied amount subtracted, floored at zero, via a
 *                 query-builder update so no model events/observers fire.
 *
 * Records are replayed newest-first (id DESC) so a child row (e.g. a review) is deleted before
 * the parent it references (its author) — authors are logged first, so their deletes run last.
 */
class BatchRollback
{
    public static function run(FakerBatch $batch): void
    {
        $batch->records()->orderByDesc('id')->cursor()->each(function (FakerRecord $record) {
            $class = $record->subject_type;
            if (! class_exists($class)) {
                return;
            }

            if ($record->action === 'decrement') {
                self::applyDecrement($class, $record->subject_id, (string) $record->column, (int) $record->amount);

                return;
            }

            $model = $class::query()->find($record->subject_id);
            if (! $model) {
                return;
            }

            if (in_array(SoftDeletes::class, class_uses_recursive($model), true)) {
                $model->forceDelete();
            } else {
                $model->delete();
            }
        });
    }

    private static function applyDecrement(string $class, string $id, string $column, int $amount): void
    {
        if ($column === '' || $amount <= 0) {
            return;
        }

        /** @var Model $probe */
        $probe = new $class;
        $table = $probe->getTable();
        $key = $probe->getKeyName();

        $current = DB::table($table)->where($key, $id)->value($column);
        if ($current === null) {
            return;
        }

        DB::table($table)->where($key, $id)->update([
            $column => max(0, (int) $current - $amount),
        ]);
    }
}
