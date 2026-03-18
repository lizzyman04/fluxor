<?php

namespace Fluxor\Contracts;

use Fluxor\Core\Http\Request;

interface ControllerInterface
{
    public function setRequest(Request $request): void;
    public function getRequest(): Request;
}