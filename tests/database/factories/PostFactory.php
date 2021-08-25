<?php

use Tests\Utils\Models\Post;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;
use Faker\Generator as Faker;

/* @var \Illuminate\Database\Eloquent\Factory $factory */
$factory->define(Post::class, function (Faker $faker): array {
    return [
        'title' => $faker->sentence,
        'body' => $faker->sentences(3, true),
        'user_id' => function () {
            return factory(User::class)->create()->getKey();
        },
        'parent_id' => null,
    ];
});
