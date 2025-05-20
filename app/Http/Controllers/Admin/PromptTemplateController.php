<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PromptTemplate;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth; // Jika perlu cek role eksplisit
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule; // Untuk Rule::unique


class PromptTemplateController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Otorisasi sudah ditangani oleh middleware rute 'role:Admin'
        $searchTerm = $request->query('search');
        $promptTemplates = PromptTemplate::query()
            ->when($searchTerm, function ($query, $search) {
                $query->where('template_name', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
            })
            ->orderBy('template_name', 'asc')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Admin/PromptTemplatesManage/Index', [ // Ganti nama folder jika perlu
            'promptTemplates' => $promptTemplates,
            'filters' => $request->only(['search']),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // Otorisasi sudah ditangani oleh middleware rute
        return Inertia::render('Admin/PromptTemplatesManage/Create'); // Ganti nama folder jika perlu
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
       // Otorisasi sudah ditangani oleh middleware rute
        $validated = $request->validate([
            'template_name' => 'required|string|max:255|unique:prompt_templates,template_name',
            'template_string' => 'required|string',
            'description' => 'nullable|string',
            'placeholders_defined_text' => 'nullable|string', // Terima sebagai teks dipisah koma
        ]);

        $placeholdersArray = [];
        if (!empty($validated['placeholders_defined_text'])) {
            $placeholdersArray = array_map('trim', explode(',', $validated['placeholders_defined_text']));
            $placeholdersArray = array_filter($placeholdersArray); // Hapus entri kosong
        }

        try {
            PromptTemplate::create([
                'template_name' => $validated['template_name'],
                'template_string' => $validated['template_string'],
                'description' => $validated['description'],
                'placeholders_defined' => $placeholdersArray, // Eloquent akan otomatis encode ke JSON karena cast di model
            ]);

            return redirect()->route('admin.prompt-templates.index')
                             ->with('success', 'Template Prompt berhasil dibuat.');
        } catch (\Exception $e) {
            Log::error('Error creating prompt template: ' . $e->getMessage());
            return redirect()->back()
                             ->with('error', 'Gagal membuat template prompt: ' . $e->getMessage())
                             ->withInput();
        }
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
        // Otorisasi sudah ditangani oleh middleware rute
        Log::info('Editing PromptTemplate ID:', ['id' => $promptTemplate->id, 'name' => $promptTemplate->template_name]); // Log data dasar

        $dataForVue = [
            'id' => $promptTemplate->id,
            'template_name' => $promptTemplate->template_name,
            'description' => $promptTemplate->description,
            'template_string' => $promptTemplate->template_string,
            'placeholders_defined_text' => implode(', ', $promptTemplate->placeholders_defined ?? []),
        ];

        // dd($promptTemplate, $dataForVue); // <-- DEBUG DI SINI

        return Inertia::render('Admin/PromptTemplatesManage/Edit', [
            'promptTemplate' => $dataForVue,
            // Kirim juga errors jika ada dari redirect()->back()->withErrors() sebelumnya
            // 'errors' => session('errors') ? session('errors')->getBag('default')->getMessages() : (object) [],
        ]);
    }

    // public function edit(Request $request, $promptTemplateId)
    // {
    //     // Log parameter mentah dari URL
    //     Log::info('Edit method called with raw parameter:', ['id_from_url' => $promptTemplateId]);

    //     $promptTemplate = PromptTemplate::find($promptTemplateId); // Cari manual

    //     if (!$promptTemplate) {
    //         Log::error('PromptTemplate not found with ID:', ['id' => $promptTemplateId]);
    //         abort(404, 'Template Prompt tidak ditemukan.');
    //     }

    //     // dd($promptTemplate); // Sekarang dd() di sini untuk melihat hasil find()

    //     Log::info('Editing PromptTemplate ID (manual find):', ['id' => $promptTemplate->id, 'name' => $promptTemplate->template_name]);

    //     $dataForVue = [
    //         'id' => $promptTemplate->id,
    //         'template_name' => $promptTemplate->template_name,
    //         'description' => $promptTemplate->description,
    //         'template_string' => $promptTemplate->template_string,
    //         'placeholders_defined_text' => implode(', ', $promptTemplate->placeholders_defined ?? []),
    //     ];

    //     return Inertia::render('Admin/PromptTemplatesManage/Edit', [
    //         'promptTemplate' => $dataForVue,
    //     ]);
    // }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, PromptTemplate $promptTemplate)
    {
       // Otorisasi sudah ditangani oleh middleware rute
        $validated = $request->validate([
            'template_name' => ['required','string','max:255',Rule::unique('prompt_templates')->ignore($promptTemplate->id)],
            'template_string' => 'required|string',
            'description' => 'nullable|string',
            'placeholders_defined_text' => 'nullable|string',
        ]);

        $placeholdersArray = [];
        if (!empty($validated['placeholders_defined_text'])) {
            $placeholdersArray = array_map('trim', explode(',', $validated['placeholders_defined_text']));
            $placeholdersArray = array_filter($placeholdersArray);
        }

        try {
            $promptTemplate->update([
                'template_name' => $validated['template_name'],
                'template_string' => $validated['template_string'],
                'description' => $validated['description'],
                'placeholders_defined' => $placeholdersArray,
            ]);
            return redirect()->route('admin.prompt-templates.index')
                             ->with('success', 'Template Prompt berhasil diperbarui.');
        } catch (\Exception $e) {
            Log::error('Error updating prompt template: ' . $e->getMessage(), ['id' => $promptTemplate->id]);
            return redirect()->back()
                             ->with('error', 'Gagal memperbarui template prompt: ' . $e->getMessage())
                             ->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PromptTemplate $promptTemplate)
    {
        // Otorisasi sudah ditangani oleh middleware rute
        // Pertimbangkan validasi: jangan hapus jika sedang dipakai ConfiguredPrompt
        if ($promptTemplate->configuredPrompts()->exists()) {
            return redirect()->route('admin.prompt-templates.index')
                             ->with('error', 'Tidak dapat menghapus template ini karena sedang digunakan oleh satu atau lebih Konfigurasi Prompt.');
        }

        try {
            $templateName = $promptTemplate->template_name;
            $promptTemplate->delete();
            return redirect()->route('admin.prompt-templates.index')
                             ->with('success', "Template Prompt \"{$templateName}\" berhasil dihapus.");
        } catch (\Exception $e) {
            Log::error('Error deleting prompt template: ' . $e->getMessage(), ['id' => $promptTemplate->id]);
            return redirect()->route('admin.prompt-templates.index')
                             ->with('error', 'Gagal menghapus template prompt. Terjadi kesalahan server.');
        }
    }
}
