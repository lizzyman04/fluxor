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

    public function dispatch(array $routeInfo): Response
    {
        Flow::clear();

        $this->request->setParams($routeInfo['params']);
        $this->request->setRouterPath($routeInfo['router_path']);

        Flow::setCurrentFile($routeInfo['file']);

        $result = (static function (string $file, Request $request) {
            return include $file;
        })($routeInfo['file'], $this->request);

        if ($result === 1 || $result === null) {
            $flowResponse = Flow::execute($this->request);
            if ($flowResponse !== null) {
                return $this->normalizeResponse($flowResponse);
            }
            throw new AppException('Route file did not produce a response');
        }

        return $this->normalizeResponse($result);
    }

    private function normalizeResponse(mixed $result): Response
    {
        return match (true) {
            $result instanceof Response => $result,
            \is_callable($result) => $this->normalizeResponse($result($this->request)),
            \is_array($result) || \is_object($result) => Response::json($result),
            \is_string($result) => Response::html($result),
            default => throw new AppException('Route must return a Response, callable, array, or string'),
        };
    }
}