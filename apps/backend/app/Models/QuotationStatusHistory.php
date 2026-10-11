<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class QuotationStatusHistory extends Model
{
    use BelongsToTenant, HasUuids;

    public $timestamps = false;

    protected $table = 'quotation_status_history';

    protected $fillable = ['tenant_id', 'quotation_id', 'actor_user_id', 'from_status', 'to_status', 'reason', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
