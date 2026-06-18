<?php

use Fluxor\Flow;
use Fluxor\Response;

Flow::GET()->do(fn($req) => Response::json(['id' => $req->param('id')]));
Flow::PUT()->do(fn($req) => Response::json(['updated' => $req->param('id')]));
