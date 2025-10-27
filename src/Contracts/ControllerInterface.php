<?php

namespace MVCCore\Contracts;

use MVCCore\Core\Request;
use MVCCore\Core\Response;

interface ControllerInterface
{
    public function setRequest(Request $request): void;
    public function getRequest(): Request;
}