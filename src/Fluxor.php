<?php
/**
 * Fluxor - Main Entry Point
 * 
 * This file re-exports all core classes for easier access.
 * Instead of: new Fluxor\Core\App()
 * You can:    new Fluxor\App()
 * 
 * @package Fluxor
 */

namespace Fluxor;

// =============================================================================
// Core Classes (Most Used)
// =============================================================================
class_alias(Core\App::class, App::class);                       // new Fluxor\App()
class_alias(Core\Controller::class, Controller::class);         // extends Fluxor\Controller
class_alias(Core\Http\Request::class, Request::class);          // Fluxor\Request
class_alias(Core\Http\Response::class, Response::class);        // Fluxor\Response
class_alias(Core\Http\Router::class, Router::class);            // Fluxor\Router
class_alias(Core\View::class, View::class);                     // Fluxor\View

// =============================================================================
// Routing (Flow Syntax)
// =============================================================================
class_alias(Core\Routing\Flow::class, Flow::class);             // Fluxor\Flow

// =============================================================================
// View Components
// =============================================================================
class_alias(Core\View\ViewFactory::class, ViewFactory::class);  // Fluxor\ViewFactory
class_alias(Core\View\Compilers\PhpCompiler::class, PhpCompiler::class); // Fluxor\PhpCompiler

// =============================================================================
// Exceptions
// =============================================================================
class_alias(Exceptions\AppException::class, AppException::class);           // Fluxor\AppException
class_alias(Exceptions\HttpException::class, HttpException::class);         // Fluxor\HttpException
class_alias(Exceptions\NotFoundException::class, NotFoundException::class); // Fluxor\NotFoundException
class_alias(Exceptions\ValidationException::class, ValidationException::class); // Fluxor\ValidationException

// =============================================================================
// Contracts
// =============================================================================
class_alias(Contracts\ControllerInterface::class, ControllerInterface::class); // Fluxor\ControllerInterface

// =============================================================================
// Core App Internals (For advanced usage)
// =============================================================================
class_alias(Core\App\Application::class, App\Application::class);           // Fluxor\App\Application
class_alias(Core\App\Config::class, App\Config::class);                     // Fluxor\App\Config
class_alias(Core\App\Environment::class, App\Environment::class);           // Fluxor\App\Environment
class_alias(Core\App\ExceptionHandler::class, App\ExceptionHandler::class); // Fluxor\App\ExceptionHandler

// =============================================================================
// Foundation (Service Container)
// =============================================================================
class_alias(Core\Foundation\ServiceContainer::class, Foundation\ServiceContainer::class); // Fluxor\Foundation\ServiceContainer
class_alias(Core\Foundation\ServiceProvider::class, Foundation\ServiceProvider::class);   // Fluxor\Foundation\ServiceProvider

// =============================================================================
// Helpers (Optional - for IDE autocomplete)
// =============================================================================
class_alias(Helpers\HttpStatusCode::class, HttpStatusCode::class); // Fluxor\HttpStatusCode
class_alias(Helpers\Str::class, Str::class);                       // Fluxor\Str