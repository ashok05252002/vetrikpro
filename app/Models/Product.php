<?php

namespace App\Models;

use App\Models\Concerns\IsMasterData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A product or service the company sells, with its default price and GST rate. */
class Product extends Model
{
    use IsMasterData;

    public const TYPES = ['product' => 'Product', 'service' => 'Service'];

    /** The GST slabs. */
    public const GST_RATES = [0, 5, 12, 18, 28];

    protected $fillable = ['name', 'type', 'code', 'hsn_sac', 'unit', 'price', 'gst_rate', 'description', 'is_active'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'gst_rate' => 'decimal:2',
        ];
    }

    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function isInUse(): bool
    {
        return $this->invoiceItems()->exists();
    }
}
