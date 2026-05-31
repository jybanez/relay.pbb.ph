<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class RelaySwaggerController extends Controller
{
    public function __invoke(): View
    {
        $openApiPath = public_path('relay-ui/openapi.json');
        $openApiVersion = is_file($openApiPath) ? (string) filemtime($openApiPath) : (string) time();

        return view('relay.swagger', [
            'appName' => config('app.name'),
            'openApiUrl' => asset('relay-ui/openapi.json').'?v='.$openApiVersion,
        ]);
    }
}
