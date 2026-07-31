<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    /** Persist the chosen locale in a year-long cookie and return to the previous page. */
    public function switch(Request $request, string $locale): RedirectResponse
    {
        $supported = array_keys(config('localization.supported', []));

        if (in_array($locale, $supported, true)) {
            cookie()->queue(cookie('locale', $locale, 60 * 24 * 365));
        }

        return redirect()->back();
    }
}
