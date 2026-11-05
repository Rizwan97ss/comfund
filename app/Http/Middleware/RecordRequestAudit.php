<?php

namespace App\Http\Middleware;

use App\Services\AuditLogger;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Records every HTTP request, its outcome and who made it. Never records raw paths
 * of token-bearing pages. A failure to write this entry must not fail the request,
 * because domain changes are already audited in their own transaction.
 */
class RecordRequestAudit
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = microtime(true);
        $requestId = (string) Str::uuid();
        Context::add('request_id', $requestId);

        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);

        if ($request->is('up')) {
            return $response;
        }

        try {
            $this->write($request, $response, $startedAt);
        } catch (Throwable $exception) {
            report($exception);
        }

        return $response;
    }

    private function write(Request $request, Response $response, float $startedAt): void
    {
        $status = $response->getStatusCode();
        $route = $this->routeLabel($request);

        $payload = array_filter([
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'parameters' => $this->routeParameters($request),
            'query' => $request->route()?->getName() === 'spa' ? [] : $request->query(),
            'input' => $request->isMethodSafe() ? [] : $this->input($request),
            'message' => $status >= 400 && $response instanceof JsonResponse ? Str::limit((string) ($response->getData(true)['message'] ?? ''), 240) : null,
        ], fn (mixed $value): bool => $value !== [] && $value !== null && $value !== '');

        [$category, $severity] = match (true) {
            $status >= 500 => ['security', 'error'],
            in_array($status, [401, 403, 419, 429], true) => ['security', 'warning'],
            $status >= 400 => ['access', 'notice'],
            default => ['access', 'info'],
        };

        $this->audit->record(
            action: 'http.'.Str::lower($request->method()),
            category: $category,
            severity: $severity,
            payload: $payload,
            http: ['http_method' => $request->method(), 'route' => $route, 'status_code' => $status],
        );
    }

    private function routeLabel(Request $request): string
    {
        $route = $request->route();

        if ($route === null) {
            return '(no route)';
        }

        return $route->getName() === 'spa' ? $this->safePagePath($request) : $route->uri();
    }

    /**
     * Invitation and reset links carry secrets in the path.
     */
    private function safePagePath(Request $request): string
    {
        return preg_replace('~^(invitation|reset-password)/.+$~', '$1/[REDACTED]', $request->path()) ?? '[REDACTED]';
    }

    /**
     * @return array<string, mixed>
     */
    private function routeParameters(Request $request): array
    {
        $route = $request->route();

        if ($route === null || $route->getName() === 'spa') {
            return [];
        }

        $parameters = [];

        foreach ($route->parameters() as $name => $value) {
            $parameters[$name] = is_object($value) && method_exists($value, 'getRouteKey') ? $value->getRouteKey() : $value;
        }

        return $parameters;
    }

    /**
     * @return array<string, mixed>
     */
    private function input(Request $request): array
    {
        $input = $request->except(array_keys($request->allFiles()));

        foreach ($request->allFiles() as $field => $files) {
            $input[$field] = array_map(fn (UploadedFile $file): array => [
                'name' => Str::limit($file->getClientOriginalName(), 120),
                'size' => $file->getSize(),
            ], is_array($files) ? $files : [$files]);
        }

        return $input;
    }
}
