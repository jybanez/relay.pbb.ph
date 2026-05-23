<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class RelaySwaggerController extends Controller
{
    public function __invoke(): View
    {
        return view('relay.swagger', [
            'appName' => config('app.name'),
        ]);
    }
}
