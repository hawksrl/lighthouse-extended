<?php

namespace Hawk\LighthouseExtended\Testing;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Testing\TestResponse;
use Laragraph\Utils\RequestParser;
use Nuwave\Lighthouse\GraphQL;
use Nuwave\Lighthouse\Support\Contracts\CreatesContext;
use Nuwave\Lighthouse\Support\Contracts\CreatesResponse;

trait CallGraphql
{
    protected $field;

    protected TestResponse $response;

    /**
     * Token de autorización que será usado en las Requests.
     */
    protected ?string $token;

    /**
     * Permite reestablecer el usuario que corrió la query como otro usuario (usando `$token`).
     */
    protected $previousUser;

    protected function execute(array $context = null, $runType = 'code'): TestResponse
    {
        $this->prepareForRunQuery($context ?? $this->context);

        switch ($runType) {
            case 'http':
                $response = $this->runQueryWithHttp($this->gql);
                break;
            case 'code':
            default:
                $response = $this->runQueryWithCode($this->gql);
                break;
        }

        $this->afterRunQuery();

        return $response;
    }

    public function runQueryWithHttp($query): TestResponse
    {
        return $this->postJson(
            route('graphql'),
            ['query' => $query],
            ['authorization' => $this->token]
        );
    }

    public function runQueryWithCode($query): TestResponse
    {
        $request = new Request(['query' => $query]);

        $graphQL = app(GraphQL::class);
        $requestParser = app(RequestParser::class);
        $createsContext = app(CreatesContext::class);
        $createsResponse = app(CreatesResponse::class);

        $operationOrOperations = $requestParser->parseRequest($request);
        $context = $createsContext->generate($request);

        $result = $graphQL->executeOperationOrOperations($operationOrOperations, $context);

        /** @var \Symfony\Component\HttpFoundation\Response $response */
        $response = $createsResponse->createResponse($result);

        return TestResponse::fromBaseResponse(new Response($response->getContent()));
    }

    private function prepareForRunQuery(array $context): void
    {
        $this->previousUser = Auth::user();

        $jwt = null;
        if ($user = Arr::get($context, 'as')) {
            $jwt = \JWTAuth::fromUser($user); //
        } elseif (Arr::get($context, 'token')) {
            $jwt = Arr::get($context, 'token');
        }

        if ($jwt) {
            \JWTAuth::setToken($jwt);
            \JWTAuth::authenticate();
        }

        $this->token = $jwt ? "Bearer {$jwt}" : null;
    }

    private function afterRunQuery(): void
    {
        if ($this->previousUser) {
            Auth::setUser($this->previousUser);
        } elseif ($this->token) {
            \JWTAuth::invalidate();
        }
    }

    /**
     * Convierte un array en un InputObject para graphql.
     *
     * @param $arr
     *
     * @return string
     */
    public function arrayToVariables($arr): string
    {
        $arr = json_encode($arr, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);

        // Elimina las comillas de los `keys`.
        // Sacado de: https://hdtuto.com/article/php-how-to-remove-double-quotes-from-json-array-keys
        $arr = preg_replace('/"([^"]+)"\s*:\s*/', '$1:', $arr);

        // Es necesario sacar el primer `{` y el último `}`.
        $arr = substr($arr, 1, strlen($arr)-2);

        /**
         * Remueve unos tokens que podrían haber sidos agregados vía `EnumInputTest`
         * @see \Hawk\LighthouseExtended\Testing\EnumInputTest::fromString()
         */
        $arr = str_replace('"\\\\enum\\\\', '', $arr);
        $arr = str_replace('\\\\enum\\\\"', '', $arr);

        return $arr;
    }

    /**
     * Convierte un array a la sintaxis de una query de graphql.
     * @param $arr
     *
     * @return string
     */
    public function arrayToQuery($arr): string
    {
        $query = '';
        collect($arr)
            ->map(function ($value, $key) use (&$query) {
                if (is_int($key)) {
                    $query .= "$value ";
                } else {
                    $query .= "$key { ";
                    $query .= $this->arrayToQuery($value);
                    $query .= "}";
                }
            });
        return $query;
    }
}
