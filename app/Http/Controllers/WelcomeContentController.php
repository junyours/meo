<?php

namespace App\Http\Controllers;

use App\Models\WelcomeContent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class WelcomeContentController extends Controller
{
    public function index()
    {
        $content = WelcomeContent::getActive();
        return response()->json($content);
    }

    public function update(Request $request, $id)
    {
        $content = WelcomeContent::findOrFail($id);

        $validated = $request->validate([
            'hero_title' => 'nullable|string',
            'hero_description' => 'nullable|string',
            'hero_image' => 'nullable|string',
            'hero_background_image' => 'nullable|string',
            'additional_images' => 'nullable|array',
            'slideshow_images' => 'nullable|array',
            'achievement_images' => 'nullable|array',
            'is_active' => 'nullable|boolean',
        ]);

        $content->update($validated);

        return response()->json($content);
    }

    public function uploadImage(Request $request)
    {
        $request->validate([
            'image' => 'required|image|max:5120',
            'type' => 'required|in:background,illustration,slideshow,achievement,completed_project',
        ]);

        $path = $request->file('image')->store('welcome-images', 'public');
        $url = Storage::url($path);

        return response()->json([
            'url' => $url,
            'type' => $request->type,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'hero_title' => 'nullable|string',
            'hero_description' => 'nullable|string',
            'hero_image' => 'nullable|string',
            'hero_background_image' => 'nullable|string',
            'additional_images' => 'nullable|array',
            'slideshow_images' => 'nullable|array',
            'achievement_images' => 'nullable|array',
            'is_active' => 'nullable|boolean',
        ]);

        $content = WelcomeContent::create($validated);

        return response()->json($content, 201);
    }
}
