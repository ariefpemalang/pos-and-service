<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockOpname extends Model
{
    use HasFactory;

    protected $fillable = [
        'opname_number',
        'status',
        'created_by',
        'approved_by',
        'total_items',
        'total_loss_units',
        'total_loss_cost',
        'total_gain_units',
        'total_gain_cost',
        'notes',
        'approved_at',
    ];

    protected $casts = [
        'total_items' => 'integer',
        'total_loss_units' => 'integer',
        'total_loss_cost' => 'decimal:2',
        'total_gain_units' => 'integer',
        'total_gain_cost' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockOpnameItem::class, 'stock_opname_id');
    }
}
