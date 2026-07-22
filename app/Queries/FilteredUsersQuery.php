<?php

namespace App\Queries;

use App\Data\FilterDefinition;
use App\Data\GroupData;
use App\Models\User;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * The single entry point to the users query. Every consumer — table render,
 * live Preview Count, saved-filter execution — builds through here, so filtering
 * and sorting are defined once. State lives in the Livewire component; this layer
 * only translates a validated Definition into a runnable query.
 *
 * The pipeline is total: a null Definition means unfiltered, and any non-null
 * Definition is an already-validated {@see GroupData}, so it cannot fail on shape.
 */
final class FilteredUsersQuery
{
    /**
     * @return QueryBuilder<User>
     */
    public function builder(?GroupData $definition, ?string $sort = null): QueryBuilder
    {
        $parameters = ['sort' => $sort];

        if ($definition !== null) {
            $parameters['filter'] = [
                'tree' => FiltersDefinitionTree::encode(FilterDefinition::toEnvelope($definition)),
            ];
        }

        $request = Request::create('/users', 'GET', $parameters);

        return QueryBuilder::for(User::class, $request)
            ->allowedFilters(AllowedFilter::custom('tree', new FiltersDefinitionTree)->delimiter(''))
            ->allowedSorts('name', 'email', 'status', 'country', 'created_at')
            ->defaultSort('name');
    }
}
