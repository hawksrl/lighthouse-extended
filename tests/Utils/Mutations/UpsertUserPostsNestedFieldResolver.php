<?php

namespace Tests\Utils\Mutations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Arr;

class UpsertUserPostsNestedFieldResolver
{
    /**
     * @var \Illuminate\Database\Eloquent\Model
     */
    protected $parent;

    /**
     * @var \Illuminate\Database\Eloquent\Relations\Relation|null
     */
    protected $parentRelation;

    public function __construct(?Model $parent = null, ?Relation $parentRelation = null)
    {
        $this->parent = $parent;
        $this->parentRelation = $parentRelation;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  \Nuwave\Lighthouse\Execution\Arguments\ArgumentSet  $args
     */
    public function __invoke($model, $args)
    {
        if (isset($args->arguments['syncUpsert'])) {
            $this->parent->posts()->delete();

            foreach ($args->arguments['syncUpsert']->value as $idx => $arg) {
                $arg->addValue('body', Arr::get($arg->toArray(), 'title').Arr::get($arg->toArray(), 'body'));

                $arg->addValue('title', 'overridden title');

                $model->create(array_merge(
                    $arg->toArray(),
                    [
                        'user_id' => $this->parent->id,
                    ]
                ));
            }
        }
    }
}
