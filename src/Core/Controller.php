<?php

namespace MVCCore\Core;

use MVCCore\Contracts\ControllerInterface;

abstract class Controller implements ControllerInterface
{
    protected Request $request;

    public function setRequest(Request $request): void
    {
        $this->request = $request;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function json($data, int $statusCode = 200): Response
    {
        return Response::json($data, $statusCode);
    }

    public function success($data = null, string $message = 'Success'): Response
    {
        return Response::success($data, $message);
    }

    public function error(string $message = 'Error', int $statusCode = 400): Response
    {
        return Response::error($message, $statusCode);
    }

    public function view(string $view, array $data = []): Response
    {
        return Response::view($view, $data);
    }

    public function redirect(string $url, int $statusCode = 302): Response
    {
        return Response::redirect($url, $statusCode);
    }

    public function index() {}
    public function show($id) {}
    public function create() {}
    public function store() {}
    public function edit($id) {}
    public function update($id) {}
    public function destroy($id) {}
}