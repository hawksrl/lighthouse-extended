<?php

namespace Hawk\LighthouseExtended\Testing;

class MutationTest extends GraphqlTest
{
    public function __construct(string $mutation, array $variables = null, array $query = [], array $context = [])
    {
        parent::__construct();

        $this->mutation = $mutation;
        $this->variables = $variables;

        $gqlVariables = $this->arrayToVariables($this->variables);

        if (!empty($query)) {
            $query = $this->arrayToQuery($query);
        }

        $gql = "
        mutation {
            $mutation ";
        if ($gqlVariables) {
            $gql .= " ( $gqlVariables ) ";
        }
        if ($query) {
            $gql .= " { $query } ";
        }
        $gql .= "}";

        $this->gql = $gql;
        $this->context = $context;
        $this->response = $this->execute();
        $this->data = $this->response->json("data.{$this->mutation}");
        $this->errors = $this->response->json('errors');

        return $this;
    }
}
