<?php

namespace App\Domain\Commercial;

use App\Models\PriceList;
use App\Models\Product;
use App\Models\ProductPrice;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

final class PriceService
{
    public function applicable(Product $product, PriceList $list, CarbonInterface $date): ProductPrice
    {
        if ($product->tenant_id !== $list->tenant_id || $product->status !== 'active' || $list->is_active !== true) {
            throw ValidationException::withMessages(['product' => 'El producto o la lista no está disponible.']);
        }

        $price = ProductPrice::forTenant($product->tenant_id)
            ->where('product_id', $product->id)->where('price_list_id', $list->id)
            ->where(fn ($query) => $query->whereNull('valid_from')->orWhereDate('valid_from', '<=', $date))
            ->where(fn ($query) => $query->whereNull('valid_until')->orWhereDate('valid_until', '>=', $date))
            ->first();
        if (! $price) {
            throw ValidationException::withMessages(['product' => 'No hay precio vigente para este producto.']);
        }

        return $price;
    }
}
