<?php

namespace Hawk\LighthouseExtended\Support\Mutations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Nuwave\Lighthouse\Execution\Arguments\ArgumentSet;
use Nuwave\Lighthouse\Execution\Arguments\ResolveNested;
use Nuwave\Lighthouse\Execution\Arguments\SaveModel;
use Nuwave\Lighthouse\Execution\Arguments\UpsertModel;
use Nuwave\Lighthouse\Support\Utils;

class MutationExecutor
{
    public static function executeUpsert(Model $model, ArgumentSet $args)
    {
        $upsert = new ResolveNested(self::makeExecutionFunction());

        return Utils::applyEach(
            static function (ArgumentSet $argumentSet) use ($upsert, $model) {
                return $upsert($model, $argumentSet);
            },
            $args
        );
    }

    protected static function makeExecutionFunction(?Relation $parentRelation = null): callable
    {
        return new UpsertModel(new SaveModel($parentRelation));
    }
}
