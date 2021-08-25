<?php

namespace Tests\Utils\Queries;

use Illuminate\Support\Arr;
use Tests\Utils\Models\Post;

class PostsByUserPaginatedQuery
{
    public function resolve($root, array $args)
    {
        return Post::query()
            ->where('user_id', Arr::get($args, 'userId'))
            ->paginate(
                Arr::get($args, 'first', 15),
                ['*'],
                'page',
                Arr::get($args, 'page', 1)
            );
    }
}
