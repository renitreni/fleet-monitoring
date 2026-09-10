<?php

namespace App\Http\Controllers;

use App\Models\Trip;
use App\Services\MapDesigns;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Response;

class AdminMapDesignController extends Controller
{
    public function show(MapDesigns $mapDesigns): Response
    {
        $this->authorize('create', Trip::class);

        return inertia('MapSettings/Show', [
            'settings' => $mapDesigns->settings(),
            'catalog' => MapDesigns::CATALOG,
            'updateUrl' => route('admin.map-settings.update'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorize('create', Trip::class);
        $data = $request->validate([
            'enabled' => ['required', 'array', 'list', 'min:1', 'max:3'],
            'enabled.*' => ['required', 'string', 'distinct', Rule::in(array_column(MapDesigns::CATALOG, 'id'))],
            'default' => ['required', 'string', Rule::in(array_column(MapDesigns::CATALOG, 'id')), 'in_array:enabled.*'],
        ], [
            'enabled.required' => 'Keep at least one map design enabled.',
            'default.in_array' => 'Choose an enabled map design as the default.',
        ]);

        DB::table('map_design_settings')->upsert([
            'id' => 1,
            'enabled' => json_encode($data['enabled'], JSON_THROW_ON_ERROR),
            'default_design' => $data['default'],
        ], ['id'], ['enabled', 'default_design']);

        return redirect()->route('admin.map-settings.show')->with('success', 'Map settings updated.');
    }
}
