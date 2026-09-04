<?php

namespace App\Http\Controllers;

use App\Models\Car;
use App\Models\OilSuggestion;
use App\Services\OpenRouterService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class OilSuggestionsController extends Controller
{
    public function __construct(
        protected OpenRouterService $openRouter
    ) {}

    /**
     * Display the cached oil brand and specification suggestions for the car.
     */
    public function index(Request $request, Car $car): Response
    {
        $this->authorize('view', $car);

        $suggestion = OilSuggestion::where('source_hash', OilSuggestion::sourceHashFor($car))->first();

        return inertia('Cars/OilSuggestions', [
            'car' => $car->only(['id', 'make', 'model', 'year', 'country', 'current_mileage']),
            'suggestion' => $suggestion,
        ]);
    }

    /**
     * Generate oil suggestions via the AI and cache them forever.
     *
     * Results are deduplicated by a hash of the vehicle specification
     * (make/model/year/country), so the AI API is called at most once per
     * unique specification. Cached results are served forever without any
     * additional API calls — there is intentionally no force-regenerate option.
     */
    public function generate(Request $request, Car $car): RedirectResponse
    {
        $this->authorize('view', $car);

        $sourceHash = OilSuggestion::sourceHashFor($car);

        // Serve the cached suggestion forever — never hit the API twice for
        // the same vehicle specification.
        if (OilSuggestion::where('source_hash', $sourceHash)->exists()) {
            return redirect()
                ->route('cars.oil-suggestions.index', $car)
                ->with('success', 'Using cached oil suggestions.');
        }

        $suggestions = $this->openRouter->getOilSuggestions(
            $car->make,
            $car->model,
            $car->year,
            $car->country,
            $car->current_mileage,
        );

        if ($suggestions === null) {
            return redirect()
                ->route('cars.oil-suggestions.index', $car)
                ->with('error', 'We could not fetch oil suggestions right now. Please try again later.');
        }

        try {
            OilSuggestion::create([
                'car_id' => $car->id,
                'source_hash' => $sourceHash,
                'suggestions_json' => $suggestions,
            ]);
        } catch (QueryException) {
            // A concurrent request already cached the same specification —
            // the unique index keeps us safe, so just serve the cached copy.
        }

        return redirect()
            ->route('cars.oil-suggestions.index', $car)
            ->with('success', 'Oil suggestions were generated and saved.');
    }
}
