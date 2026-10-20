<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'company_id', 'document_type', 'consecutive', 'document_number', 'issued_on', 'point_of_sale', 'net_total'])]
class Invoice extends Model
{
    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'consecutive' => 'integer',
            'issued_on' => 'date',
            'net_total' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'number' => $this->document_number,
            'issued_on' => $this->issued_on->toDateString(),
            'point_of_sale' => $this->point_of_sale,
            'net_total' => (float) $this->net_total,
            'registered_at' => $this->created_at->toIso8601String(),
        ];
    }
}
