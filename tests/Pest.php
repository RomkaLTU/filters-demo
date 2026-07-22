<?php

use App\Data\FilterDefinition;
use App\Data\FilterTreeNormalizer;
use App\Models\User;
use App\Queries\FilteredUsersQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\QueryBuilder\QueryBuilder;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

// Unit tests boot the application (laravel-data validation needs the container)
// but never touch the database.
pest()->extend(TestCase::class)
    ->in('Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Wrap a root group in the `{version, root}` Definition envelope.
 *
 * @param  array<string, mixed>  $root
 * @return array<string, mixed>
 */
function filterEnvelope(array $root, mixed $version = 1): array
{
    return ['version' => $version, 'root' => $root];
}

/**
 * Build a group node. Pass an $id to model a draft node the UI would carry.
 *
 * @param  list<array<string, mixed>>  $children
 * @return array<string, mixed>
 */
function filterGroup(string $boolean, array $children, ?string $id = null): array
{
    $node = ['type' => 'group', 'boolean' => $boolean, 'children' => $children];

    return $id === null ? $node : [...$node, 'id' => $id];
}

/**
 * Build a condition node. Pass an $id to model a draft node the UI would carry.
 *
 * @return array<string, mixed>
 */
function filterCondition(string $field, string $operator, string $value, ?string $id = null): array
{
    $node = ['type' => 'condition', 'field' => $field, 'operator' => $operator, 'value' => $value];

    return $id === null ? $node : [...$node, 'id' => $id];
}

/**
 * A canonical root group wrapping a single condition — the common one-clause filter.
 *
 * @return array<string, mixed>
 */
function conditionRoot(string $field, string $operator, string $value): array
{
    return filterGroup('and', [filterCondition($field, $operator, $value)]);
}

/**
 * Build the users query through the real Phase 3 pipeline for a canonical root
 * array (null root = unfiltered), validating it at the Apply boundary first.
 *
 * @param  array<string, mixed>|null  $root
 * @return QueryBuilder<User>
 */
function filteredUsers(?array $root, ?string $sort = null): QueryBuilder
{
    $definition = $root === null ? null : FilterDefinition::fromRoot($root);

    return app(FilteredUsersQuery::class)->builder($definition, $sort);
}

/**
 * Normalize a draft then build the users query — the Apply path end to end.
 *
 * @param  array<string, mixed>  $draft
 * @return QueryBuilder<User>
 */
function filteredUsersFromDraft(array $draft): QueryBuilder
{
    return filteredUsers(FilterTreeNormalizer::normalize($draft));
}

/**
 * The emails a filter selects, sorted — the shape pipeline assertions compare.
 *
 * @param  QueryBuilder<User>  $query
 * @return list<string>
 */
function selectedEmails(QueryBuilder $query): array
{
    /** @var list<string> */
    return $query->pluck('email')->sort()->values()->all();
}
