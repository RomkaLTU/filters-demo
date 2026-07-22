<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('loads the key authenticated pages without JavaScript errors', function () {
    $this->actingAs(User::factory()->create());

    $pages = visit([
        '/dashboard',
        '/users',
        '/settings/profile',
        '/settings/appearance',
    ]);

    $pages->assertNoJavaScriptErrors();
});
