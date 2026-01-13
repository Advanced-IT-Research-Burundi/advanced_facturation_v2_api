<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppConfig;
use App\Services\ObrService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AppConfigController extends Controller
{
    public $obrService ;
    public function __construct(ObrService $obr){
        $this->obrService = $obr;
    }
    /**
     * Display a listing of app configs.
     */
    public function index()
    {
       return $this->obrService->getToken();
        return response()->json([
            'success' => true,
            'data' => AppConfig::paginate(15)
        ], Response::HTTP_OK);
    }

    /**
     * Store a newly created app config.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'config_key' => 'required|string|unique:app_configs|max:255',
            'value' => 'nullable|string',
            'description' => 'nullable|string',
            'modifiable' => 'nullable|boolean',
            'user_id' => 'nullable|exists:users,id',
        ]);

        $appConfig = AppConfig::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'App config created successfully',
            'data' => $appConfig
        ], Response::HTTP_CREATED);
    }

    /**
     * Display the specified app config.
     */
    public function show(AppConfig $appConfig)
    {
        return response()->json([
            'success' => true,
            'data' => $appConfig
        ], Response::HTTP_OK);
    }

    /**
     * Update the specified app config.
     */
    public function update(Request $request, AppConfig $appConfig)
    {
        $validated = $request->validate([
            'config_key' => 'sometimes|required|string|unique:app_configs,config_key,' . $appConfig->id . '|max:255',
            'value' => 'nullable|string',
            'description' => 'nullable|string',
            'modifiable' => 'nullable|boolean',
            'user_id' => 'nullable|exists:users,id',
        ]);

        $appConfig->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'App config updated successfully',
            'data' => $appConfig
        ], Response::HTTP_OK);
    }

    /**
     * Remove the specified app config (soft delete).
     */
    public function destroy(AppConfig $appConfig)
    {
        $appConfig->delete();

        return response()->json([
            'success' => true,
            'message' => 'App config deleted successfully'
        ], Response::HTTP_OK);
    }

    /**
     * Restore a soft-deleted app config.
     */
    public function restore($id)
    {
        $appConfig = AppConfig::withTrashed()->findOrFail($id);
        $appConfig->restore();

        return response()->json([
            'success' => true,
            'message' => 'App config restored successfully',
            'data' => $appConfig
        ], Response::HTTP_OK);
    }

    /**
     * Get app config by key.
     */
    public function getByKey($key)
    {
        $appConfig = AppConfig::where('config_key', $key)->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $appConfig
        ], Response::HTTP_OK);
    }
}
