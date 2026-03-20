<?php

namespace Fluxor\Core\Http\Router;

use Fluxor\Core\Http\Request;
use Fluxor\Core\Http\Response;
use Fluxor\Core\Routing\Flow;
use Fluxor\Exceptions\AppException;

class Dispatcher
{
    private Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function dispatch(array $routeInfo): void
    {
        $this->request->setParams($routeInfo['params']);
        $this->request->setRouterPath($routeInfo['router_path']);

        try {
            $result = include $routeInfo['file'];

            if ($result === 1 || $result === null) {
                $flowResponse = Flow::execute($this->request);
                if ($flowResponse !== null) {
                    $this->sendResponse($flowResponse);
                }
                return;
            }

            $this->sendResponse($this->normalizeResponse($result));

        } catch (\Throwable $e) {
            throw $e;
        }
    }

    private function normalizeResponse($result): Response
    {
        return match (true) {
            is_callable($result) => $result($this->request),
            $result instanceof Response => $result,
            is_array($result) || is_object($result) => Response::json($result),
            is_string($result) => Response::html($result),
            default => throw new AppException('Route must return a callable, Response, or convertible value')
        };
    }

    private function sendResponse($response): void
    {
        if ($response instanceof Response) {
            $response->send();
        } elseif (is_array($response) || is_object($response)) {
            Response::json($response)->send();
        } elseif (is_string($response)) {
            echo $response;
        }
    }
}