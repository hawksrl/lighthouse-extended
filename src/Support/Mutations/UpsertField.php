<?php

namespace Hawk\LighthouseExtended\Support\Mutations;

use Illuminate\Database\Eloquent\Model;

class UpsertField
{
    private string $mutatorClass;

    protected string|null $modelClass = null;

    protected $result;

    public function __construct(string $mutatorClass, string $modelClass = null)
    {
        $this->mutatorClass = $mutatorClass;
        $this->modelClass = $modelClass;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Model|null  $parent
     * @param  \Nuwave\Lighthouse\Execution\Arguments\ArgumentSet|array  $argumentSet
     * @return mixed
     * @throws \Exception
     */
    public function __invoke(Model|null $parent, \Nuwave\Lighthouse\Execution\Arguments\ArgumentSet|array $argumentSet): mixed
    {
        try {
            /** @var \Hawk\LighthouseExtended\Support\Mutations\Mutator $mutator */
            $mutator = app($this->mutatorClass, [
                'input' => $argumentSet->toArray(),
                'argumentSet' => $argumentSet,
            ]);
            $mutator->withRoot($parent);
            if ($this->modelClass) {
                $mutator->withModelClass($this->modelClass);
            }
            $mutator->validate();

            $this->result = $mutator->mutate();
            if ($this->result instanceof Model) {
                $this->result->refresh();
            }
            return $this->result;
        } catch (\Exception $e) {
            throw $e;
        }
    }

    public function result()
    {
        return $this->result;
    }
}
