<?php

namespace App\Services;

use App\Models\Food;
use App\Models\FoodAlias;
use App\Models\FoodPortion;
use App\Models\Recipe;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Searches foods (by name and alias) and recipes together.
 *
 * Queries are Hungarian and often partial ("zab" should find "zabpehely", "zabtej" and USDA "Oats"
 * through its alias), so matching is substring-based rather than FULLTEXT: FULLTEXT only matches word
 * prefixes, which misses Hungarian compounds ("tej" in "zabtej") and ignores words under 3 characters
 * ("só"). The utf8mb4_unicode_ci collation makes matching case- and accent-insensitive ("rantott"
 * finds "rántott").
 */
class FoodSearch
{
    /** Rank for a name that contains the query but matches it in no better way. */
    private const int SUBSTRING_RANK = 3;

    /**
     * @return Collection<int, FoodSearchResult>
     */
    public function search(string $query, int $limit = 10): Collection
    {
        $query = Str::squish($query);

        if ($query === '') {
            return collect();
        }

        $tokens = explode(' ', $query);

        return $this->searchFoods($query, $tokens, $limit)
            ->concat($this->searchRecipes($query, $tokens, $limit))
            ->sortBy([
                fn (FoodSearchResult $a, FoodSearchResult $b) => $a->matchRank <=> $b->matchRank,
                fn (FoodSearchResult $a, FoodSearchResult $b) => mb_strlen($a->name) <=> mb_strlen($b->name),
                fn (FoodSearchResult $a, FoodSearchResult $b) => strcmp($a->name, $b->name),
            ])
            ->take($limit)
            ->values();
    }

    /**
     * @param  list<string>  $tokens
     * @return Collection<int, FoodSearchResult>
     */
    private function searchFoods(string $query, array $tokens, int $limit): Collection
    {
        [$nameRank, $nameBindings] = $this->rankExpression('foods.name', $query);
        [$aliasRank, $aliasBindings] = $this->rankExpression('food_aliases.name', $query);

        $foods = Food::query()
            ->select('foods.*')
            ->selectRaw(
                "LEAST({$nameRank}, COALESCE((SELECT MIN({$aliasRank}) FROM food_aliases WHERE food_aliases.food_id = foods.id), ?)) AS match_rank",
                [...$nameBindings, ...$aliasBindings, self::SUBSTRING_RANK],
            )
            ->where(function (Builder $builder) use ($tokens): void {
                foreach ($tokens as $token) {
                    $pattern = $this->containsPattern($token);

                    $builder->where(fn (Builder $tokenMatch) => $tokenMatch
                        ->where('foods.name', 'like', $pattern)
                        ->orWhereHas('aliases', fn (Builder $alias) => $alias->where('name', 'like', $pattern)));
                }
            })
            ->with(['aliases', 'portions'])
            ->orderBy('match_rank')
            ->orderByRaw('CHAR_LENGTH(foods.name)')
            ->limit($limit)
            ->get();

        return $foods->map(fn (Food $food): FoodSearchResult => new FoodSearchResult(
            type: 'food',
            id: $food->id,
            name: $food->name,
            brand: $food->brand,
            source: $food->source->value,
            aliases: $food->aliases->map(fn (FoodAlias $alias): string => $alias->name)->values()->all(),
            per100g: $food->nutrientsPer100g(),
            defaultPortionG: $food->serving_size_g,
            portions: $this->portions($food->portions),
            matchRank: (int) $food->match_rank,
        ));
    }

    /**
     * @param  list<string>  $tokens
     * @return Collection<int, FoodSearchResult>
     */
    private function searchRecipes(string $query, array $tokens, int $limit): Collection
    {
        [$nameRank, $nameBindings] = $this->rankExpression('recipes.name', $query);

        $recipes = Recipe::query()
            ->select('recipes.*')
            ->selectRaw("{$nameRank} AS match_rank", $nameBindings)
            ->where(function (Builder $builder) use ($tokens): void {
                foreach ($tokens as $token) {
                    $builder->where('recipes.name', 'like', $this->containsPattern($token));
                }
            })
            ->with(['items.food', 'portions'])
            ->orderBy('match_rank')
            ->orderByRaw('CHAR_LENGTH(recipes.name)')
            ->limit($limit)
            ->get();

        return $recipes->map(fn (Recipe $recipe): FoodSearchResult => new FoodSearchResult(
            type: 'recipe',
            id: $recipe->id,
            name: $recipe->name,
            brand: null,
            source: null,
            aliases: [],
            per100g: $recipe->nutrientsPer100g(),
            defaultPortionG: $recipe->default_portion_g,
            portions: $this->portions($recipe->portions),
            matchRank: (int) $recipe->match_rank,
        ));
    }

    /**
     * 0 = exact name, 1 = name starts with the query, 2 = a later word starts with it, 3 = contains it.
     *
     * @return array{string, list<string>}
     */
    private function rankExpression(string $column, string $query): array
    {
        $escaped = $this->escapeLike($query);

        return [
            "CASE WHEN {$column} = ? THEN 0 WHEN {$column} LIKE ? THEN 1 WHEN {$column} LIKE ? THEN 2 ELSE ".self::SUBSTRING_RANK.' END',
            [$query, "{$escaped}%", "% {$escaped}%"],
        ];
    }

    private function containsPattern(string $token): string
    {
        return '%'.$this->escapeLike($token).'%';
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    /**
     * @param  Collection<int, FoodPortion>  $portions
     * @return list<array{label: string, grams: float}>
     */
    private function portions(Collection $portions): array
    {
        return $portions
            ->map(fn (FoodPortion $portion): array => ['label' => $portion->label, 'grams' => $portion->grams])
            ->values()
            ->all();
    }
}
