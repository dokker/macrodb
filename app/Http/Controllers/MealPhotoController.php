<?php

namespace App\Http\Controllers;

use App\Services\PhotoDraftBuilder;
use App\Services\Vision\VisionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MealPhotoController extends Controller
{
    /**
     * Returns a draft only; nothing is saved. The client corrects it and approves it through POST /meals.
     */
    public function store(Request $request, PhotoDraftBuilder $builder): JsonResponse
    {
        $validated = $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $image = $request->file('image');

        try {
            $draft = $builder->build($image->getContent(), $image->getMimeType(), $validated['note'] ?? null);
        } catch (VisionException $exception) {
            report($exception);

            return response()->json(['message' => 'A képfelismerés most nem elérhető.'], 502);
        }

        return response()->json(['data' => $draft]);
    }
}
