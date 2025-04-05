<?php

namespace App\Exceptions;

use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Laravel\Lumen\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\HttpException;
use GuzzleHttp\Exception\ClientException; // Added for HTTP client errors
use Throwable;

class Handler extends ExceptionHandler
{
    use ApiResponser;

    /**
     * A list of the exception types that should not be reported.
     *
     * @var array
     */
    protected $dontReport = [
        AuthorizationException::class,
        AuthenticationException::class,
        HttpException::class,
        ModelNotFoundException::class,
        ValidationException::class,
        ClientException::class, // Added to prevent reporting HTTP client errors
    ];

    /**
     * Report or log an exception.
     *
     * @param  \Throwable  $exception
     * @return void
     *
     * @throws \Exception
     */
    public function report(Throwable $exception)
    {
        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Throwable  $exception
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     *
     * @throws \Throwable
     */
    public function render($request, Throwable $exception)
    {
        // Handle ClientException (e.g., from HTTP requests to other services)
        if ($exception instanceof ClientException) {
            $message = $exception->getResponse()->getBody()->getContents();
            $code = $exception->getCode();
            return $this->errorResponse($message, $code); // Use the actual HTTP status code
        }

        // HTTP Exception (e.g., 404 Not Found)
        if ($exception instanceof HttpException) {
            $code = $exception->getStatusCode();
            $message = Response::$statusTexts[$code] ?? 'Unknown HTTP Error';
            return $this->errorResponse($message, $code);
        }

        // Model Not Found Exception
        if ($exception instanceof ModelNotFoundException) {
            $model = strtolower(class_basename($exception->getModel()));
            return $this->errorResponse(
                "Does not exist any instance of {$model} with the given ID",
                Response::HTTP_NOT_FOUND
            );
        }

        // Validation Exception
        if ($exception instanceof ValidationException) {
            $errors = $exception->validator->errors()->getMessages();
            return $this->errorResponse($errors, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Authorization Exception (Access to Forbidden)
        if ($exception instanceof AuthorizationException) {
            return $this->errorResponse($exception->getMessage(), Response::HTTP_FORBIDDEN);
        }

        // Authentication Exception (Unauthorized Access)
        if ($exception instanceof AuthenticationException) {
            return $this->errorResponse($exception->getMessage(), Response::HTTP_UNAUTHORIZED);
        }

        // If running in development, show the full error
        if (env('APP_DEBUG', false)) {
            return parent::render($request, $exception);
        }

        // Generic Internal Server Error
        return $this->errorResponse('Unexpected error. Try again later.', Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}