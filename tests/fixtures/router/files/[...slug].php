<?php

use Fluxor\Core\Routing\Flow;
use Fluxor\Core\Http\Response;

Flow::GET()->do(fn($req) => Response::json(['slug' => $req->param('slug')]));
