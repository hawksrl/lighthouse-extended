<?php

namespace Tests\Utils\Mutations;

use Hawk\LighthouseExtended\Support\Mutations\HkMutation;
use Hawk\LighthouseExtended\Support\Mutations\MutationExecutor;
use Illuminate\Support\Collection;

class UpsertUserPostsField extends HkMutation
{
    public function authorize()
    {
        return true;
    }

    public function setRules()
    {
        $this->rules = [
            'upsert' => [
                '*' => [
                    'title' => [
                        'required' => __('Ingresa un título'),
                    ],
                    'body' => [
                        'required' => __('Ingresa un cuerpo'),
                    ],
                ],
            ],
        ];
    }

    public function mutate(): Collection
    {
        $posts = collect();

        foreach ($this->argumentSet->arguments['upsert']->value as $idx => $upsertPostArgumentSet) {
            $upsertPostArgumentSet->addValue('title', 'overridden title');
            $upsertPostArgumentSet->addValue('body', $this->input("upsert.$idx.title").$this->input("upsert.$idx.body"));
            $posts->push(MutationExecutor::executeUpsert(new $this->modelClass([
                'user_id' => $this->root->id,
            ]), $upsertPostArgumentSet));
        }

        return $posts;
    }
}
