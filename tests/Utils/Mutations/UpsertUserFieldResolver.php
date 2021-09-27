<?php

namespace Tests\Utils\Mutations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class UpsertUserFieldResolver
{
    /**
     * @var \Illuminate\Database\Eloquent\Model
     */
    protected $model;

    protected array $args;

    public function __construct(?Model $model = null, array $args = [])
    {
        $this->model = $model;
        $this->args = $args;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  \Nuwave\Lighthouse\Execution\Arguments\ArgumentSet  $args
     */
    public function __invoke($model, $args)
    {
        return $model->create(array_merge(
            $args->toArray(),
            [
                'email' => Str::lower(Arr::get($args->toArray(), 'email')),
            ]
        ));
    }
}
