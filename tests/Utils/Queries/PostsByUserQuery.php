<?php

namespace Tests\Utils\Queries;

use Illuminate\Support\Arr;
use Tests\Utils\Models\Post;

class PostsByUserQuery
{
    public function resolve($root, array $args)
    {
        return Post::query()->where('user_id', Arr::get($args, 'userId'))->get();
    }
}
