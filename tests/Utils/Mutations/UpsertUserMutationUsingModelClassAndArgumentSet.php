<?php

namespace Tests\Utils\Mutations;

use Hawk\LighthouseExtended\Support\Mutations\HkMutation;
use Hawk\LighthouseExtended\Support\Mutations\MutationExecutor;
use Tests\Utils\Models\User;

class UpsertUserMutationUsingModelClassAndArgumentSet extends HkMutation
{
    public function authorize()
    {
        return true;
    }

    public function setRules()
    {
        $this->rules = [
            'name' => [
                'required' => __('Ingresa un nombre'),
            ],
            'email' => [
                'required' => __('Ingresa un E-mail'),
                'email' => __('No es un E-mail válido'),
            ],
        ];
    }

    public function mutate()
    {
        return MutationExecutor::executeUpsert(new $this->modelClass, $this->argumentSet);
    }
}
