<?php

namespace Tests\Utils\Mutations;

use Hawk\LighthouseExtended\Support\Mutations\HkMutation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class CreateUserMutation extends HkMutation
{
    public function authorize()
    {
        return optional(Auth::user())->hasRole('admin');
    }

    public function setRules()
    {
        $this->rules = [
            'word' => 'required',
        ];
    }

    public function mutate(): string
    {
        return strtoupper($this->input('word'));
    }
}
