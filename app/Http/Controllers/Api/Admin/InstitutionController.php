<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Institution;
use Illuminate\Http\Request;

/**
 * Admin-only access to the admin's own institution profile.
 * institution_id is always read from the authenticated user,
 * never from the request body.
 */
class InstitutionController extends Controller
{
    /**
     * GET /api/admin/institution
     */
    public function show(Request $request)
    {
        $institution = Institution::findOrFail($request->user()->institution_id);

        return response()->json($institution);
    }

    /**
     * PUT /api/admin/institution
     * Whitelisted editable fields only — identifiers like slug are
     * immutable through this endpoint by construction.
     */
    public function update(Request $request)
    {
        $institution = Institution::findOrFail($request->user()->institution_id);

        $data = $request->validate([
            'name'                      => 'sometimes|string|max:255',
            'short_name'                => 'sometimes|string|max:50',
            'city'                      => 'sometimes|string|max:100',
            'province'                  => 'sometimes|string|max:100',
            'description'               => 'sometimes|nullable|string|max:5000',
            'application_deadline'      => 'sometimes|date',
            'is_accepting_applications' => 'sometimes|boolean',
            'student_number_prefix' => 'sometimes|nullable|string|max:20|regex:/^\d*$/',
            'student_number_length' => 'sometimes|integer|min:4|max:20',
            'application_fee' => 'sometimes|numeric|min:20|max:10000',
        ]);

        $institution->update($data);

        return response()->json($institution->fresh());
    }
}