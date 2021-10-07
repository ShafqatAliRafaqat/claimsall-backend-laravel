<?php

namespace App\Exceptions;

use Exception;
use App\Http\Libraries\ResponseBuilder;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;

class Handler extends ExceptionHandler {

    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
            //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'password',
        'password_confirmation',
    ];

    /**
     * Report or log an exception.
     *
     * This is a great spot to send exceptions to Sentry, Bugsnag, etc.
     *
     * @param  \Exception  $exception
     * @return void
     */
    public function report(Exception $exception) {
        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Exception  $exception
     * @return \Illuminate\Http\Response
     */
    public function render($request, Exception $exception) {
        //         findOrFail Exception handler
        if ($request->expectsJson() && $exception instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
            return (new ResponseBuilder(404, __('Sorry, We did not find your record.')))->build();
        }
        // Validator validation fail Exception handling
        if ($request->expectsJson() && $exception instanceof \Illuminate\Validation\ValidationException) {
            $errors = $exception->validator->errors()->getMessages();
            $firstErrorMessage = array_first($errors);
            return (new ResponseBuilder(400, __($firstErrorMessage[0])))->build();
        }
        
        // For 404 routes
        if ($request->expectsJson() && $exception instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException) {
            return (new ResponseBuilder(404, __('Requested API not found!')))->build();
        }
//         convert remainging all Exception into JSON formate
        if ($request->expectsJson()) {
            //Ignore cases
            if (in_array(get_class($exception), ['Illuminate\Auth\AuthenticationException'])) {
                goto end;
            }
            return (new ResponseBuilder(422, (($exception->getMessage()) ? $exception->getMessage() : __('Something Went Wrong'))))->build();
        }
        end:
        return parent::render($request, $exception);
    }

    /**
     * Convert an authentication exception into an unauthenticated response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Illuminate\Auth\AuthenticationException  $exception
     * @return \Illuminate\Http\Response
     */
    protected function unauthenticated($request, \Illuminate\Auth\AuthenticationException $exception) {
        if ($request->expectsJson()) {
            return (new ResponseBuilder(498, __('Unauthenticated')))->build();

            //return (new ResponseBuilder(401, __('Unauthenticated')))->build();
        }

        return redirect()->guest(route('login'));
    }

}
