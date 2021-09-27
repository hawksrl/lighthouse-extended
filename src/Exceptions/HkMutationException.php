<?php

namespace Hawk\LighthouseExtended\Exceptions;

use Exception;
use Nuwave\Lighthouse\Exceptions\RendersErrorsExtensions;

class HkMutationException extends Exception implements RendersErrorsExtensions
{
    public $output;

    public function __construct(string $message, array $errors)
    {
        parent::__construct($message);
        $this->output = collect($errors)
            ->map(function ($error) {
                return collect($error)->flatten();
            });
    }

    public function isClientSafe()
    {
        return true;
    }

    public function getCategory()
    {
        return 'validation';
    }

    public function extensionsContent(): array
    {
        return ['validation' => $this->output];
    }
}
