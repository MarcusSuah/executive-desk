<?php

namespace App\Http\Controllers;

use App\Support\SignInSettings;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BrandingController extends Controller
{
    /**
     * Serve the sign-in background image chosen by an administrator.
     *
     * This is public because the sign-in page is shown before authentication.
     */
    public function loginBackground(): StreamedResponse
    {
        $path = SignInSettings::backgroundPath();

        abort_if($path === null, 404);

        return Storage::disk('local')->response($path, null, [
            'Cache-Control' => 'public, max-age=604800',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
