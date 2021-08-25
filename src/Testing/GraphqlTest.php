<?php

namespace Hawk\LighthouseExtended\Testing;

use Illuminate\Foundation\Testing\Concerns\MakesHttpRequests;
use Illuminate\Support\Arr;
use Illuminate\Testing\TestResponse;

class GraphqlTest
{
    use CallGraphql, MakesHttpRequests;

    protected $app;

    protected $mutation;

    protected array $variables;

    protected $input;

    /**
     * Respuesta devuelta por graphql.
     * @var
     */
    protected TestResponse $response;

    /**
     * Objeto `data.$mutation` devuelto por graphql.
     */
    protected mixed $data;

    /**
     * Errores de graphql.
     * @var
     */
    protected array|null $errors;

    protected string $gql;

    protected array $context;

    public function __construct()
    {
        $this->app = app();
    }

    public function dump()
    {
        dump($this->response->json());
        return $this;
    }

    public function getData()
    {
        return $this->data;
    }

    public function getErrors()
    {
        return $this->errors;
    }

    public function __get($name)
    {
        return Arr::get($this->data, $name);
    }

    public function instance(string $modelClass, string $primaryKey = 'id', $arg = 'id')
    {
        if ($primaryKey === 'uuid') {
            return $modelClass::whereUuid(Arr::get($this->data, $arg ?? $primaryKey))->firstOrFail();
        }

        return $modelClass::findOrFail(Arr::get($this->data, $primaryKey));
    }

    public function variable($key = null)
    {
        if (!$key) {
            return $this->variables;
        }
        return Arr::get($this->variables, $key);
    }

    public function result(string $key = null)
    {
        if (!$key) {
            return $this->getData();
        }
        return Arr::get($this->getData(), $key);
    }

    public function refetch()
    {
        $this->response = $this->execute();
        $this->data = $this->response->json("data.{$this->field}");
        $this->errors = $this->response->json('errors');
    }
}
