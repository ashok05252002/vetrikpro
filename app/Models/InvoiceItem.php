<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One line of an invoice. Amounts are computed by InvoiceCalculator, never typed. */
class InvoiceItem extends Model
{
    protected $fillable = [
        'product_id', 'description', 'hsn_sac', 'quantity', 'unit', 'unit_price', 'discounted_price',
        'gst_rate', 'taxable', 'tax', 'amount', 'position',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'discounted_price' => 'decimal:2',
            'gst_rate' => 'decimal:2',
            'taxable' => 'decimal:2',
            'tax' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
