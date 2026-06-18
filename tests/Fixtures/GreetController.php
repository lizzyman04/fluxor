<?php

namespace Fluxor\Tests\Fixtures;

use Fluxor\Core\Controller;
use Fluxor\Core\Http\Request;
use Fluxor\Core\Http\Response;

class GreetController extends Controller
{
    public function show(Request $request): Response
    {
        return Response::json([
            'received_request' => $request instanceof Request,
            'id' => $request->param('id'),
        ]);
    }
}
