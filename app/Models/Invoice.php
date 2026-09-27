<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Support\Clock;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Invoice extends Model
{
    public const REFERENCE_PREFIX = 'INV';

    protected $fillable = [
        'customer_id', 'bill_name', 'bill_email', 'bill_address', 'bill_gstin', 'place_of_supply', 'is_interstate', 'charge_tax',
        'issue_date', 'due_date', 'notes', 'terms', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'is_interstate' => 'boolean',
            'charge_tax' => 'boolean',
            'issue_date' => 'date:Y-m-d',
            'due_date' => 'date:Y-m-d',
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'taxable_total' => 'decimal:2',
            'cgst_total' => 'decimal:2',
            'sgst_total' => 'decimal:2',
            'igst_total' => 'decimal:2',
            'total' => 'decimal:2',
            'issued_at' => 'datetime',
            'sent_at' => 'datetime',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('position');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** "INV-0007", or "Draft" before it is issued. */
    public function reference(): string
    {
        return $this->number === null ? 'Draft' : self::REFERENCE_PREFIX.'-'.str_pad((string) $this->number, 4, '0', STR_PAD_LEFT);
    }

    public function isDraft(): bool
    {
        return $this->status === InvoiceStatus::Draft;
    }

    public function isOverdue(): bool
    {
        return $this->status === InvoiceStatus::Sent && $this->due_date !== null && $this->due_date->lt(Clock::today());
    }

    /**
     * Issue a draft: give it the next number and mark it sent. Numbers are
     * taken under a lock on the invoices table's highest number, with the
     * unique index as backstop, so two people issuing at once cannot collide.
     */
    public function issue(): void
    {
        DB::transaction(function () {
            $fresh = static::query()->lockForUpdate()->findOrFail($this->id);

            if ($fresh->number === null) {
                $next = (int) static::query()->lockForUpdate()->max('number') + 1;
                $this->forceFill(['number' => $next]);
            }

            // Issued, not emailed: sent_at / sent_to are set only when an email goes out.
            $this->forceFill(['status' => InvoiceStatus::Sent, 'issued_at' => $this->issued_at ?? now()])->save();
        });
    }
}
