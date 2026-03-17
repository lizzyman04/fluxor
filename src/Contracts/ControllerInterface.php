<?php

namespace Fluxor\Contracts;

use Fluxor\Core\Request;
use Fluxor\Core\Response;

interface ControllerInterface
{
    public function setRequest(Request $request): void;
    public function getRequest(): Request;
}