<?php

namespace App\Models\Sales;

use App\Models\User;
use App\Models\Finance\FinanceRecord;
use App\Traits\RecordsFinancialMovements;
use App\Traits\HasAttachments;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CreditNote extends Model
{
    use HasFactory, SoftDeletes, RecordsFinancialMovements, HasAttachments;

    protected $table = 'credit_notes';

    protected $fillable = [
        'sale_id',
        'document_number',
        'sri_access_key',
        'sri_status',
        'sri_authorization_date',
        'sri_error',
        'subtotal',
        'tax_amount',
        'total',
        'subtotal_iva_15',
        'subtotal_iva_0',
        'reason',
        'restore_stock',
        'reverse_balance',
        'xml_path',
        'pdf_path',
        'user_id',
    ];

    protected $casts = [
        'sri_authorization_date' => 'datetime',
        'subtotal'               => 'decimal:2',
        'tax_amount'             => 'decimal:2',
        'total'                  => 'decimal:2',
        'subtotal_iva_15'        => 'decimal:2',
        'subtotal_iva_0'         => 'decimal:2',
        'restore_stock'          => 'boolean',
        'reverse_balance'        => 'boolean',
    ];

    /**
     * Factura original a la que se le emite la Nota de Crédito.
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'sale_id');
    }

    /**
     * Usuario que emite la Nota de Crédito.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
