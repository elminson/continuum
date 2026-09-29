<?php

namespace App\Http\Controllers;

use App\Models\Region;
use Inertia\Inertia;
use Inertia\Response;

class ApiDocsController extends Controller
{
    public function __invoke(): Response
    {
        $example = Region::query()->preset()->orderBy('id')->first();

        return Inertia::render('ApiDocs', [
            'baseUrl' => rtrim((string) config('app.url'), '/').'/api/v1',
            'exampleSlug' => $example !== null ? $example->slug : 'gran-chaco',
            'exampleBbox' => $example !== null ? $example->bbox()->toFirmsString() : '-70,-35,-55,-15',
        ]);
    }
}
