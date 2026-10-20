<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\Response;

class FrontendController extends Controller
{
    public function __invoke(): Response
    {
        $index = config('app.frontend_index');

        if (! is_string($index) || ! is_file($index)) {
            return response()->view('welcome');
        }

        return response(file_get_contents($index), 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'no-cache',
        ]);
    }
}
