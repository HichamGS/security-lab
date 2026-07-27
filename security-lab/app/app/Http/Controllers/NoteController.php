<?php

namespace App\Http\Controllers;

use App\Models\Note;
use Illuminate\Http\Request;

class NoteController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Only show notes belonging to the authenticated user
        $notes = $request->user()->notes()->get();
        return response()->json($notes);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
        ]);

        $note = $request->user()->notes()->create([
            'title' => $request->title,
            'content' => $request->content,
        ]);

        return response()->json($note, 201);
    }

    /**
     * Display the specified resource.
     * Protected by NotePolicy - only owner can view
     */
    public function show(Note $note)
    {
        $this->authorize('view', $note);
        return response()->json($note);
    }

    /**
     * Update the specified resource in storage.
     * Protected by NotePolicy - only owner can update
     */
    public function update(Request $request, Note $note)
    {
        $this->authorize('update', $note);

        $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'content' => 'sometimes|required|string',
        ]);

        $note->update($request->only(['title', 'content']));

        return response()->json($note);
    }

    /**
     * Remove the specified resource from storage.
     * Protected by NotePolicy - only owner can delete
     */
    public function destroy(Note $note)
    {
        $this->authorize('delete', $note);
        $note->delete();

        return response()->json(null, 204);
    }
}
