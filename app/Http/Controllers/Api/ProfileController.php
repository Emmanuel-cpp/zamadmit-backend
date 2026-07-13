<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    /**
     * Update the student's profile.
     * PUT /api/profile
     */
    public function update(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'first_name'    => 'sometimes|string|max:255',
            'last_name'     => 'sometimes|string|max:255',
            'nrc'           => 'sometimes|string|unique:users,nrc,' . $user->id,
            'phone'         => 'sometimes|string|max:20',
            'province'      => 'sometimes|string|max:100',
            'date_of_birth' => 'sometimes|date',
            'interests'     => 'sometimes|nullable|array|max:10',
            'interests.*'   => 'string|max:100',
        ]);

        $user->fill($data);
        $user->name = ($data['first_name'] ?? $user->first_name)
                    . ' '
                    . ($data['last_name'] ?? $user->last_name);
        $user->save();

        // Check and update profile completion status
        $user->profile_complete = $this->isProfileComplete($user);
        $user->save();

        return response()->json([
            'user'    => $user->fresh(),
            'message' => 'Profile updated successfully.',
        ]);
    }

    /**
     * Determine if the user's profile meets the minimum bar for applying.
     */
    private function isProfileComplete($user): bool
    {
        $hasPersonalInfo = $user->first_name
            && $user->last_name
            && $user->nrc
            && $user->phone
            && $user->province
            && $user->date_of_birth;

        // Profile is complete only when ALL 4 verified documents exist:
        // NRC front, NRC back, Grade 12 certificate, passport photo
        $requiredTypes = ['nrc_front', 'nrc_back', 'certificate', 'photo'];
        $verifiedCount = \App\Models\Document::where('user_id', $user->id)
            ->whereIn('type', $requiredTypes)
            ->where('verification_status', 'verified')
            ->distinct('type')
            ->count('type');

        $hasDocuments = $verifiedCount >= count($requiredTypes);

        $hasGrades = $user->grades()->count() > 0;

        return (bool) ($hasPersonalInfo && $hasDocuments && $hasGrades);
    }

    /**
 * Save the student's grades.
 * POST /api/profile/grades
 */
public function saveGrades(Request $request)
{
    $user = $request->user();

    $request->validate([
        'grades'           => 'required|array|min:1',
        'grades.*.subject' => 'required|string|max:100',
        'grades.*.grade'   => 'required|integer|min:1|max:9',
    ]);

    // Delete existing grades and replace with new ones
    $user->grades()->delete();

    foreach ($request->grades as $gradeData) {
        $user->grades()->create([
            'subject' => $gradeData['subject'],
            'grade'   => $gradeData['grade'],
        ]);
    }

    // Re-check profile completion
    $user->profile_complete = $this->isProfileComplete($user);
    $user->save();

    return response()->json([
        'message' => 'Grades saved successfully.',
        'grades'  => $user->grades()->get(),
    ]);
}
}