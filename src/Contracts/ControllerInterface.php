<?php

namespace Fluxor\Contracts;

/**
 * Marker interface for controllers.
 *
 * Controllers receive the Request as an argument to their action method
 * (e.g. `public function show(Request $req)`), so it is an input rather than
 * mutable controller state. This interface intentionally declares no methods.
 */
interface ControllerInterface
{
}
