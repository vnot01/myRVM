<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PromptTemplate;
use Illuminate\Http\Request;
use Inertia\Inertia;         // Import Inertia


class PromptTemplateController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Otorisasi dasar (hanya Admin)
        if (auth()->user()->role !== 'Admin') {
            abort(403, 'ANDA TIDAK DIIZINKAN MENGAKSES HALAMAN INI.');
        }

        $searchTerm = $request->query('search');

        $promptTemplates = PromptTemplate::query()
            ->when($searchTerm, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            })
            ->orderBy('is_active', 'desc') // Tampilkan yang aktif dulu
            ->orderBy('name', 'asc')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Admin/Prompts/Index', [
            'promptTemplates' => $promptTemplates,
            'filters' => $request->only(['search']),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(PromptTemplate $promptTemplate)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(PromptTemplate $promptTemplate)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, PromptTemplate $promptTemplate)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PromptTemplate $promptTemplate)
    {
        //
    }
}
