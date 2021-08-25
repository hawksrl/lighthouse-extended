<?php

namespace Hawk\LighthouseExtended\Traits;

use Hawk\LighthouseExtended\Testing\MutationTest;
use Hawk\LighthouseExtended\Testing\QueryTest;

trait TestGraphql
{
    public function gql()
    {
        return $this;
    }

    public function mutation(string $mutation, array $input = null, array $query = [], array $context = [])
    {
        return new MutationTest($mutation, $input, $query, $context);
    }

    public function query(string $field, array $variables = [], array $query = [], array $context = [])
    {
        return new QueryTest($field, $variables, $query, $context);
    }
}
