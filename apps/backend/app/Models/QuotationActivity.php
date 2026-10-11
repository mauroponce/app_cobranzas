<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class QuotationActivity extends Model
{
    use BelongsToTenant, HasUuids;

    protected $table = 'quotation_activities';

    protected $fillable = ['tenant_id', 'quotation_id', 'user_id', 'type', 'notes', 'occurred_at', 'next_follow_up_at'];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime', 'next_follow_up_at' => 'datetime'];
    }
}
