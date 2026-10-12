<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditAssessment extends Model
{
    use BelongsToTenant, HasUuids;

    protected $table = 'credit_assessments';

    protected $fillable = ['tenant_id', 'sale_application_id', 'status', 'requested_amount', 'approved_amount', 'approved_down_payment', 'approved_financing_charge', 'approved_installment_count', 'approved_frequency', 'reviewer_id', 'review_notes', 'reviewed_at'];

    public function application(): BelongsTo
    {
        return $this->belongsTo(SaleApplication::class, 'sale_application_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    protected function casts(): array
    {
        return ['requested_amount' => 'decimal:2', 'approved_amount' => 'decimal:2', 'approved_down_payment' => 'decimal:2', 'approved_financing_charge' => 'decimal:2', 'reviewed_at' => 'datetime'];
    }
}
