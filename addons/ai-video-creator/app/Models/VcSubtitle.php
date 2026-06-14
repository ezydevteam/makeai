<?php

namespace Addons\AiVideoCreator\Models;

use Illuminate\Database\Eloquent\Model;

class VcSubtitle extends Model
{
    protected $fillable = [
        'vc_render_id', 'provider', 'status', 'language', 'format',
        'content', 'segments', 'credits_deducted', 'error_message',
    ];

    protected function casts(): array
    {
        return [
            'segments' => 'array',
            'credits_deducted' => 'float',
        ];
    }

    public function render()
    {
        return $this->belongsTo(VcRender::class, 'vc_render_id');
    }
}
