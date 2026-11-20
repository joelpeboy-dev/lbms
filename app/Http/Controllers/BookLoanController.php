<?php

namespace App\Http\Controllers;

use App\Models\BookLoan;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;

class BookLoanController extends Controller
{
    /**
     * Display a listing of the resource.
     * GET /api/book-loans
     */
    public function index()
    {
        try {
            $loans = BookLoan::all();

            return response()->json([
                'success' => true,
                'data' => $loans,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve records.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     * POST /api/book-loans
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'book_id' => 'required|exists:books,id',
                'user_id' => 'required|exists:users,id',
                'borrowed_at' => 'required|date',
                'due_date' => 'required|date|after_or_equal:borrowed_at',
            ]);

            $loan = BookLoan::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Book loan created successfully.',
                'data' => $loan,
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create record.',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Display the specified resource.
     * GET /api/book-loans/{id}
     */
    public function show($id)
    {
        try {
            // Using findOrFail ensures a 404 response is cleanly caught if the ID doesn't exist
            $loan = BookLoan::findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $loan,
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Book loan record not found.',
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     * PATCH /api/book-loans/{id}
     */
    public function update(Request $request, $id)
    {
        try {
            $loan = BookLoan::findOrFail($id);

            $validated = $request->validate([
                'status' => 'sometimes|string|in:borrowed,returned,overdue',
                'returned_at' => 'nullable|date',
            ]);

            $loan->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Book loan updated successfully.',
                'data' => $loan,
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Book loan record not found.',
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update record.',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Remove the specified resource from storage.
     * DELETE /api/book-loans/{id}
     */
    public function destroy($id)
    {
        try {
            $loan = BookLoan::findOrFail($id);
            $loan->delete();

            return response()->json([
                'success' => true,
                'message' => 'Book loan deleted successfully.',
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Book loan record not found.',
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete record.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
