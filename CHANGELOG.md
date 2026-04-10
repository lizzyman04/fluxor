# Changelog

All notable changes to Fluxor Core will be documented in this file.

## [1.0.2] - 2026-04-10

### Added
- Route compilation with persistent file-based cache
- Catch-all parameters via `[...slug]` syntax
- `405 Method Not Allowed` response with `Allow` header
- Request attributes for middleware data passing
- Route specificity ordering (static → dynamic → catch-all)

### Changed
- Router passes HTTP method to Matcher for method-aware dispatching
- Dispatcher returns `Response` instead of sending directly
- ErrorHandler methods return `Response` instead of calling `exit`
- Route file `include` isolated in static closure
- `getJsonBody()` guards against empty input

### Fixed
- `preg_quote` called before placeholder substitution breaking dynamic parameters
- Groups `(name)` now correctly transparent in URL patterns
- `scandir()` non-determinism resolved by compile-time sort

## [1.0.1] - 2026-04-07

### Added
- CORS support with global and per-route configuration
- Fetch HTTP client (cURL-based, zero dependencies)
- Config locking mechanism for critical settings
- Global `fetch()` helper function

### Changed
- PHP 8.4 compatibility with explicit nullable types
- Array spread operator replacing `array_merge()`
- String interpolation replacing concatenation
- Global namespace prefix for internal PHP functions

### Fixed
- Headers already sent warning in ExceptionHandler
- Nullable parameter type in Response::download()

## [1.0.0] - 2026-03-25

### Added
- File-based routing inspired by Next.js
- Flow syntax for route definitions
- MVC architecture with controllers and views
- View system with layouts, sections, and stacks
- CSRF protection and secure sessions
- Environment configuration with `.env` support
- Zero external dependencies
- Global helper functions (`base_path()`, `env()`, `abort()`, etc.)

[1.0.2]: https://github.com/lizzyman04/fluxor/releases/tag/1.0.2
[1.0.1]: https://github.com/lizzyman04/fluxor/releases/tag/1.0.1
[1.0.0]: https://github.com/lizzyman04/fluxor/releases/tag/1.0.0