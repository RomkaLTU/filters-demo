<?php

use App\Data\ConditionData;
use App\Data\FilterNodeData;
use App\Data\GroupData;

arch('domain enums are string-backed')
    ->expect('App\Enums')
    ->toBeStringBackedEnums();

arch('concrete data DTOs are final')
    ->expect('App\Data')
    ->toBeFinal()
    ->ignoring(FilterNodeData::class);

arch('the query layer never depends on Livewire or the UI')
    ->expect('App\Queries')
    ->not->toUse(['Livewire', 'Flux']);

arch('no debugging helpers are left in the codebase')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'vd'])
    ->not->toBeUsed();

arch('env() is read only through config')
    ->expect('env')
    ->not->toBeUsedIn('App');

test('filter DTOs declare only readonly properties', function (string $dto) {
    $properties = array_filter(
        (new ReflectionClass($dto))->getProperties(),
        fn (ReflectionProperty $property): bool => $property->getDeclaringClass()->getName() === $dto,
    );

    expect($properties)->not->toBeEmpty();

    foreach ($properties as $property) {
        expect($property->isReadOnly())->toBeTrue(
            sprintf('%s::$%s must be readonly.', $dto, $property->getName()),
        );
    }
})->with([
    FilterNodeData::class,
    ConditionData::class,
    GroupData::class,
]);
