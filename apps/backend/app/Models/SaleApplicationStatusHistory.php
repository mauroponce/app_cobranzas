<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleApplicationStatusHistory extends Model
{
    use BelongsToTenant, HasUuids;

    public const UPDATED_AT = null;

    protected $table = 'sale_application_status_history';

    protected $fillable = ['tenant_id', 'sale_application_id', 'actor_id', 'from_status', 'to_status', 'reason'];

    public function application(): BelongsTo
    {
        return $this->belongsTo(SaleApplication::class, 'sale_application_id');
    }
}
