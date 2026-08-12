<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Item */
class ItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $photoUrl = $this->photo_url;
        if ($photoUrl && ! str_starts_with($photoUrl, 'http://') && ! str_starts_with($photoUrl, 'https://')) {
            $photoUrl = url($photoUrl);
        }

        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'photo_url' => $photoUrl,
            'category_id' => $this->category_id,
            'unit_id' => $this->unit_id,
            'category' => $this->whenLoaded('category', fn () => $this->category ? [
                'id' => $this->category->id,
                'name' => $this->category->name,
            ] : null),
            'unit' => $this->whenLoaded('unit', fn () => $this->unit ? [
                'id' => $this->unit->id,
                'name' => $this->unit->name,
                'abbreviation' => $this->unit->abbreviation,
            ] : null),
            'stock' => (int) $this->stock,
            'stock_opname' => (int) $this->stock_opname,
            'is_low_stock' => $this->isLowStock(),
            'purchase_price' => (float) $this->purchase_price,
            'selling_price' => (float) $this->selling_price,
            'description' => $this->description,
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
