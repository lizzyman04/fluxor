<?php

use Fluxor\Flow;
use Fluxor\Response;

Flow::GET()->do(fn($req) => Response::json(['post' => $req->param('id')]));
