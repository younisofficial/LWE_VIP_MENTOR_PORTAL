<?php

/*
| Every Feature test runs on a fresh in-memory database (see phpunit.xml).
*/
pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

pest()->extend(Tests\TestCase::class)->in('Unit');
