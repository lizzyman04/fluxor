<?php

namespace Fluxor\Core;

use Fluxor\Contracts\ControllerInterface;

/**
 * Base controller.
 *
 * Action methods receive the Request directly as an argument, e.g.
 * `public function show(Request $request)`. The Request is passed in by the
 * router/Flow rather than being stored as controller state.
 */
abstract class Controller implements ControllerInterface
{
}
