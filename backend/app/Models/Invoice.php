<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'company_id', 'point_of_sale_id', 'document_type', 'consecutive', 'document_number', 'issued_on', 'point_of_sale', 'net_total', 'tickets'])]
class Invoice extends Model
{
    /**
     * Boletas que otorga una factura: una por cada múltiplo completo del valor por boleta.
     */
    public static function ticketsFor(float $netTotal, int $ticketValue): int
    {
        if ($ticketValue < 1 || $netTotal < $ticketValue) {
            return 0;
        }

        return (int) floor($netTotal / $ticketValue);
    }

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'point_of_sale_id' => 'integer',
            'consecutive' => 'integer',
            'issued_on' => 'date',
            'net_total' => 'decimal:2',
            'tickets' => 'integer',
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
            'consecutive' => (int) $this->consecutive,
            'issued_on' => $this->issued_on->toDateString(),
            'point_of_sale' => $this->point_of_sale,
            'net_total' => (float) $this->net_total,
            'tickets' => (int) $this->tickets,
            'registered_at' => $this->created_at->toIso8601String(),
        ];
    }
}
