<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CollectionDeclarationComponent extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = ['tenant_id', 'declaration_id', 'method', 'amount', 'reference'];

    protected function casts(): array { return ['amount' => 'decimal:2']; }
}
