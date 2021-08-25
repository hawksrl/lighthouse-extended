<?php

namespace Hawk\LighthouseExtended\Support\Mutations;

use Exception;
use Hawk\ArrayRules\HkArrayRules;
use Hawk\LighthouseExtended\Exceptions\HkGraphqlError;
use Hawk\LighthouseExtended\Exceptions\HkMutationException;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Execution\Arguments\ArgumentSet;
use Symfony\Component\HttpFoundation\ParameterBag;

abstract class HkMutation extends FormRequest implements Mutator
{
    /** @var \Illuminate\Contracts\Validation\Validator */
    public $validator;

    protected Collection|array $rules = [];

    protected Collection $input;

    protected ArgumentSet $argumentSet;

    protected mixed $root;

    protected string|null $modelClass = null;

    public function __construct(array|ArgumentSet $input, ArgumentSet $argumentSet)
    {
        parent::__construct();

        $this->setInput($input);
        $this->setArgumentSet($argumentSet);
        $this->withRoot(null);
    }

    public function withRoot($root): self
    {
        $this->root = $root;

        return $this;
    }

    public function withModelClass(string $model): self
    {
        $this->modelClass = $model;

        return $this;
    }

    /**
     * Devuelve las reglas en el formato que Laravel espera.
     */
    public function rules(): array
    {
        if ($this->rules instanceof Collection) {
            $this->rules = $this->rules->toArray();
        }

        return HkArrayRules::parseRules($this->rules);
    }

    /**
     * Mergea $rules con las reglas existentes.
     */
    public function mergeRules(array $rules): Collection
    {
        $this->rules = collect($this->rules ?? []);

        return $this->rules = $this->rules->mergeRecursive($rules);
    }

    /**
     * Devuelve los mensajes en el formato que Laravel espera.
     */
    public function messages(): array
    {
        if ($this->rules instanceof Collection) {
            $this->rules = $this->rules->toArray();
        }

        return HkArrayRules::parseMessages($this->rules);
    }

    /**
     * Get the validator instance for the request.
     */
    protected function getValidatorInstance(): \Illuminate\Validation\Validator
    {
        $factory = app(ValidationFactory::class);

        $validator = $this->createDefaultValidator($factory);

        if (method_exists($this, 'moreValidation')) {
            $validator->after(function () {
                $this->moreValidation();
            });
        }

        $this->validator = $validator;

        return $validator;
    }

    /**
     * Create the default validator instance.
     */
    protected function createDefaultValidator(ValidationFactory $factory): Validator
    {
        return $factory->make($this->json()->all(), $this->rules(), $this->messages(), $this->attributes());
    }

    /**
     * Dispara la excepción que indica que la validación ha fallado.
     *
     * @param Validator $validator
     *
     * @throws HkMutationException
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HkMutationException(__('Los valores ingresados son inválidos'), json_decode($validator->getMessageBag(), true));
    }

    /**
     * Realiza las validaciones.
     *
     * @throws \Hawk\LighthouseExtended\Exceptions\HkMutationException|\Hawk\LighthouseExtended\Exceptions\HkGraphqlError
     */
    public function validate(): array
    {
        if (! $this->rules || empty($this->rules)) {
            $this->setRules();
        }

        if (! $this->authorize()) {
            throw new HkGraphqlError(__('No puedes realizar esta operación'));
        }

        try {
            return $this->getValidatorInstance()->validate();
        } catch (Exception $e) {
            $this->failedValidation($this->getValidatorInstance());
        }
    }

    /**
     * Agrega un error a la lista de errores de `Validator`.
     */
    public function addError(string $key, string $message = 'invalid'): void
    {
        $this->validator->errors()->add($key, json_encode([
            'message' => $message,
        ]));
    }

    /**
     * Enforces JSON request.
     */
    public function isJson(): bool
    {
        return true;
    }

    /**
     * Indica si esta `request` está creando un recurso.
     */
    public function isCreating(): bool
    {
        return $this->input('id') == null;
    }

    /**
     * Indica si esta `request` está actualizando un recurso.
     */
    public function isUpdating(): bool
    {
        return ! $this->isCreating();
    }

    public function setInput(array|ArgumentSet $input): self
    {
        $this->input = collect($input instanceof ArgumentSet ? $input->toArray() : $input);

        return $this;
    }

    public function setArgumentSet(ArgumentSet $argumentSet): self
    {
        $this->argumentSet = $argumentSet;

        return $this;
    }

    public function getInput(): Collection
    {
        return $this->input;
    }

    public function setJson($json): Mutator
    {
        if (! $json instanceof ParameterBag) {
            $json = new ParameterBag($json);
        }

        return parent::setJson($json);
    }

    public function validateResolved(): void
    {
        // This `request` is not validated when resolving, but when executing.
    }
}
