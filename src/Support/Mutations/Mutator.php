<?php

namespace Hawk\LighthouseExtended\Support\Mutations;

use Illuminate\Support\Collection;

interface Mutator
{
    public function authorize();

    public function setRules();

    public function mutate();

    public function setJson($json): Mutator;

    public function setInput(array $input): Mutator;

    public function getInput(): Collection;
}
