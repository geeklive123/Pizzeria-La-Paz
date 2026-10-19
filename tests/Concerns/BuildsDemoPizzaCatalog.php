<?php

namespace Tests\Concerns;

use App\Enums\ProductType;
use App\Models\Category;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProductVariant;

trait BuildsDemoPizzaCatalog
{
    protected function ensureDemoPizzaFlavors(Company $company, int $target = 17): void
    {
        $category = Category::query()->forCompany($company)->where('name', 'Pizzas')->firstOrFail();
        $existing = Product::query()->forCompany($company)->where('type', ProductType::Pizza)->count();

        for ($number = $existing + 1; $number <= $target; $number++) {
            $product = Product::query()->create([
                'company_id' => $company->getKey(),
                'category_id' => $category->getKey(),
                'name' => sprintf('Pizza Demo %02d', $number),
                'type' => ProductType::Pizza,
                'is_active' => true,
            ]);

            foreach ([
                'Personal' => ['personal', '25.00', 0],
                'Mediana' => ['mediana', '45.00', 1],
                'Familiar' => ['familiar', '70.00', 2],
            ] as $name => [$sizeKey, $price, $sortOrder]) {
                ProductVariant::query()->create([
                    'company_id' => $company->getKey(),
                    'product_id' => $product->getKey(),
                    'name' => $name,
                    'size_key' => $sizeKey,
                    'price' => $price,
                    'requires_preparation' => true,
                    'is_active' => true,
                    'sort_order' => $sortOrder,
                ]);
            }
        }
    }
}
