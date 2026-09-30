<?php

namespace App\Http\Controllers;

use App\Services\Insights\EngineeringShowcase;
use Inertia\Inertia;
use Inertia\Response;

class EngineeringController extends Controller
{
    public function __invoke(EngineeringShowcase $showcase): Response
    {
        return Inertia::render('Engineering', $showcase->get());
    }
}
