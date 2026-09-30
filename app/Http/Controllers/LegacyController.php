<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\Response;

final class LegacyController extends Controller
{
    public function handle(): Response
    {
        require base_path('legacy/public/index.php');

        return new Response();
    }
}
