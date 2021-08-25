<?php

use Tests\Utils\Models\Team;
use Tests\Utils\Models\User;
use Faker\Generator as Faker;
use Tests\Utils\Models\Company;

/* @var \Illuminate\Database\Eloquent\Factory $factory */
$factory->define(User::class, function (Faker $faker): array {
    return [
        'name' => $faker->name,
        'email' => $faker->unique()->safeEmail,
        'password' => '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', // secret
    ];
});
