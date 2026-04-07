<?php

namespace Fluxor\Core;

use Fluxor\Core\Http\Request;
use Fluxor\Contracts\ControllerInterface;

abstract class Controller implements ControllerInterface
{
    protected Request $request;

    public function setRequest(Request $request): void
    {
        $this->request = $request;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }
}