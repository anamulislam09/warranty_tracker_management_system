<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Every API answer has the same shape, and only TWO http codes are used:
 *   success -> HTTP 200   { "success": true,  "message": "...", "data": ..., "meta": { pagination } }
 *   failure -> HTTP 422   { "success": false, "code": "...", "message": "...", "errors": { field: [..] } }
 * The app reads "success" (true / false). "code" tells WHAT went wrong, "message" is the text to show.
 * (Errors that are not thrown from a controller - wrong token, no permission, wrong URL - also leave as 422: see bootstrap/app.php)
 */
abstract class ApiController extends Controller
{
    protected function ok($data = null, string $message = 'OK', ?array $meta = null): JsonResponse
    {
        $body = ['success' => true, 'message' => $message, 'data' => $data];
        if ($meta !== null) $body['meta'] = $meta;

        return response()->json($body, 200);
    }

    /** A new record was saved. Still HTTP 200, like every success. */
    protected function created($data, string $message = 'Saved successfully.'): JsonResponse
    {
        return $this->ok($data, $message);
    }

    /** Something could not be done. Always HTTP 422. */
    protected function fail(string $message, string $code, ?array $errors = null): JsonResponse
    {
        $body = ['success' => false, 'code' => $code, 'message' => $message];
        if ($errors) $body['errors'] = $errors;

        return response()->json($body, 422);
    }

    /** One model through its resource, as a plain array. */
    protected function one(string $resource, $model): array
    {
        return (new $resource($model))->resolve(request());
    }

    /** The page numbers of a list (the "meta" block). */
    protected function pageMeta(LengthAwarePaginator $page): array
    {
        return [
            'current_page' => $page->currentPage(), 'per_page' => $page->perPage(),
            'total' => $page->total(), 'last_page' => $page->lastPage(), 'has_more' => $page->hasMorePages(),
        ];
    }

    /** A paginated list: data = the items, meta = the page numbers. */
    protected function paginated(LengthAwarePaginator $page, string $resource, array $extraMeta = []): JsonResponse
    {
        return $this->ok(
            $resource::collection($page->getCollection())->resolve(request()),
            'OK',
            array_merge($this->pageMeta($page), $extraMeta)
        );
    }

    protected function perPage(Request $request, int $default = 20, int $max = 50): int
    {
        return max(1, min($max, (int) $request->input('per_page', $default)));
    }
}
