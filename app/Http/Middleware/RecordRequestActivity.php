<?php

namespace App\Http\Middleware;

use App\Services\AuditLogger;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Makes sure no activity escapes the audit trail.
 *
 * Domain services write descriptive entries (e.g. "event.rescheduled"). Any request
 * that changes something but was not audited by the code it reached, including failed
 * and refused attempts, is recorded here as "request.{route}". Page views are
 * recorded as "page.viewed" (configurable). Only route record IDs and the names of
 * submitted fields are stored, never their values.
 */
class RecordRequestActivity
{
    /**
     * Routes that are not recorded as page views (background polling and assets).
     *
     * @var array<int, string>
     */
    private const IGNORED_VIEW_ROUTES = ['offline.snapshot', 'branding.login-background', 'storage.local'];

    /**
     * Submitted fields that are omitted even by name.
     *
     * @var array<int, string>
     */
    private const IGNORED_FIELDS = ['_token', '_method'];

    public function __construct(private AuditLogger $audit) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $this->audit->startRequest();

        $response = $next($request);

        try {
            if ($this->isWrite($request)) {
                if (! $this->audit->recordedThisRequest()) {
                    $this->recordWrite($request, $response);
                }
            } elseif ($this->isPageView($request, $response)) {
                $this->audit->log('page.viewed', $this->record($request), [], [
                    'route' => $request->route()?->getName() ?? $request->path(),
                    'records' => $this->recordIds($request),
                ]);
            }
        } catch (Throwable $exception) {
            report($exception);
        }

        return $response;
    }

    /**
     * Record a state-changing request that the application did not audit itself.
     */
    private function recordWrite(Request $request, Response $response): void
    {
        $route = $request->route()?->getName() ?? $request->path();

        $this->audit->log('request.'.$route, $this->record($request), [], [
            'method' => $request->method(),
            'path' => '/'.ltrim($request->path(), '/'),
            'records' => $this->recordIds($request),
            'fields' => array_values(array_diff(array_keys($request->except(self::IGNORED_FIELDS)), self::IGNORED_FIELDS)),
            'status' => $response->getStatusCode(),
            'outcome' => $this->outcome($request, $response),
        ]);
    }

    /**
     * Describe how the request ended.
     */
    private function outcome(Request $request, Response $response): string
    {
        $status = $response->getStatusCode();

        return match (true) {
            $status === 403 => 'denied',
            $status === 404 => 'not found',
            $status === 419 => 'session expired',
            $status === 429 => 'rate limited',
            $status === 422, $request->hasSession() && $request->session()->has('errors') => 'rejected by validation',
            $status >= 500 => 'error',
            default => 'succeeded',
        };
    }

    /**
     * Determine whether the request changes state.
     */
    private function isWrite(Request $request): bool
    {
        return in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true);
    }

    /**
     * Determine whether the request is a page a person opened.
     */
    private function isPageView(Request $request, Response $response): bool
    {
        if (! config('executive-desk.audit.page_views')
            || $request->user() === null
            || $request->method() !== 'GET'
            || $response->getStatusCode() >= 300
            || $request->header('Purpose') === 'prefetch'
            || $request->hasHeader('X-Inertia-Partial-Data')
            || in_array($request->route()?->getName(), self::IGNORED_VIEW_ROUTES, true)) {
            return false;
        }

        return $request->hasHeader('X-Inertia') || Str::contains((string) $response->headers->get('Content-Type'), 'text/html');
    }

    /**
     * Get the single record the route is about, if any.
     */
    private function record(Request $request): ?Model
    {
        $models = array_values(array_filter(
            $request->route()?->parameters() ?? [],
            fn ($parameter) => $parameter instanceof Model,
        ));

        return count($models) === 1 ? $models[0] : null;
    }

    /**
     * Get the route's record identifiers, e.g. ["event" => 12].
     *
     * @return array<string, int|string>
     */
    private function recordIds(Request $request): array
    {
        $ids = [];

        foreach ($request->route()?->parameters() ?? [] as $name => $value) {
            $ids[$name] = $value instanceof Model ? $value->getKey() : (is_scalar($value) ? $value : '');
        }

        return $ids;
    }
}
