<?php

use Fluxor\Core\Routing\Flow;
use Fluxor\Core\Http\Response;

Flow::GET()->do(fn($req) => Response::json(['id' => $req->param('id')]));
Flow::PUT()->do(fn($req) => Response::json(['updated' => $req->param('id')]));
