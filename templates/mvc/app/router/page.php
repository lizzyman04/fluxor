<?php
/**
 * Homepage - GET /
 */

use MVCCore\Flow;
use Source\Controllers\HomeController;

Flow::GET()->to(HomeController::class, 'index');

return Flow::execute($req);