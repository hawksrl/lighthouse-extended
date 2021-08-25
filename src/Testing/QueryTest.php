<?php

namespace Hawk\LighthouseExtended\Testing;

class QueryTest extends GraphqlTest
{
    public function __construct(string $field, array $variables = null, array $query = [], array $context = [])
    {
        parent::__construct();

        $this->field = $field;
        $this->variables = $variables;

        $gql = "query { $field ";
        if (!empty($variables)) {
            $variables = $this->arrayToVariables($variables);
            $gql .= "( $variables ) ";
        }
        if (!empty($query)) {
            $gql .= "{ ";
            $query = $this->arrayToQuery($query);
            $gql .= "$query ";
            $gql .= "}";
        }
        $gql .= "}";

        $this->gql = $gql;
        $this->context = $context;
        $this->response = $this->execute();
        $this->data = $this->response->json("data.{$this->field}");
        $this->errors = $this->response->json('errors');

        return $this;
    }
}
