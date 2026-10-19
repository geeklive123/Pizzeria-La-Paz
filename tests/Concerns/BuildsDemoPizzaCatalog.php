<?php

namespace Tests\Concerns;

use App\Actions\RegisterOpeningStockAction;
use App\Actions\UpdateRecipeAction;
use App\Enums\InventoryMovementType;
use App\Enums\ProductType;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Company;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;

trait BuildsDemoPizzaCatalog
{
    protected function ensureDemoPizzaFlavors(
        Company $company,
        Branch $branch,
        User $actor,
        int $target = 17,
    ): void {
        $category = Category::query()->forCompany($company)->where('name', 'Pizzas')->firstOrFail();
        $sourceVariants = ProductVariant::query()
            ->forCompany($company)
            ->whereHas('product', fn ($product) => $product->where('name', 'Pizza Pepperoni'))
            ->with('recipe.items')
            ->get()
            ->keyBy('size_key');
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
                $variant = ProductVariant::query()->create([
                    'company_id' => $company->getKey(),
                    'product_id' => $product->getKey(),
                    'name' => $name,
                    'size_key' => $sizeKey,
                    'price' => $price,
                    'requires_preparation' => true,
                    'is_active' => true,
                    'sort_order' => $sortOrder,
                ]);

                $sourceRecipe = $sourceVariants->get($sizeKey)?->recipe;
                $this->assertNotNull($sourceRecipe);

                app(UpdateRecipeAction::class)->execute(
                    $company,
                    $variant,
                    $sourceRecipe->items->map(fn ($item): array => [
                        'ingredient_id' => $item->ingredient_id,
                        'component_type' => $item->component_type->value,
                        'quantity' => (string) $item->quantity,
                    ])->all(),
                    sprintf('Receta Pizza Demo %02d %s', $number, $name),
                );
            }
        }

        $masa = InventoryItem::query()
            ->forCompany($company)
            ->where('name', 'Masa')
            ->with('unit')
            ->firstOrFail();

        $hasOpeningStock = InventoryMovement::query()
            ->forCompany($company)
            ->where('branch_id', $branch->getKey())
            ->where('inventory_item_id', $masa->getKey())
            ->where('type', InventoryMovementType::Opening)
            ->exists();

        if (! $hasOpeningStock) {
            app(RegisterOpeningStockAction::class)->execute(
                $company,
                $branch,
                $masa,
                '20000.000',
                $masa->unit,
                '0.006000',
                $actor,
                referenceType: static::class,
            );
        }
    }
}
