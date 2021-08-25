<?php

namespace Hawk\LighthouseExtended\Exceptions;

use Exception;
use Nuwave\Lighthouse\Exceptions\RendersErrorsExtensions;

class HkGraphqlError extends Exception implements RendersErrorsExtensions
{
    public function isClientSafe()
    {
        return true;
    }

    public function getCategory()
    {
        return 'hkerror';
    }

    public function extensionsContent(): array
    {
        return ['message' => $this->getMessage()];
    }
}
