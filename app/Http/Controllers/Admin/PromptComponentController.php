<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PromptComponent; // Import model
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;

class PromptComponentController extends Controller
{
    // Daftar tipe komponen yang valid (bisa juga diambil dari config atau enum jika ada)
    protected $validComponentTypes = [
        'target_description',
        'condition_details',
        'label_options',
        'output_format_definition',
        'generation_config_preset'
    ];

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        //
        $searchTerm = $request->query('search');
        $typeFilter = $request->query('type');

        $promptComponents = PromptComponent::query()
            ->when($searchTerm, function ($query, $search) {
                $query->where('component_name', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%")
                      ->orWhere('content', 'like', "%{$search}%");
            })
            ->when($typeFilter, function ($query, $type) {
                if (!empty($type) && $type !== 'all') {
                    $query->where('component_type', $type);
                }
            })
            ->orderBy('component_type')->orderBy('component_name')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Admin/PromptComponentsManage/Index', [
            'promptComponents' => $promptComponents,
            'filters' => $request->only(['search', 'type']),
            'availableComponentTypes' => $this->validComponentTypes, // Kirim tipe yang valid untuk filter
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('Admin/PromptComponentsManage/Create', [
            'availableComponentTypes' => $this->validComponentTypes, // Kirim tipe yang valid untuk dropdown
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'component_name' => 'required|string|max:255|unique:prompt_components,component_name',
            'component_type' => ['required', 'string', Rule::in($this->validComponentTypes)],
            'content' => 'required|string',
            'description' => 'nullable|string',
        ]);

        // Validasi tambahan jika tipe adalah generation_config_preset (harus JSON valid)
        if ($validated['component_type'] === 'generation_config_preset') {
            $jsonValidator = Validator::make(['content_json' => $validated['content']], ['content_json' => 'json']);
            if ($jsonValidator->fails()) {
                return redirect()->back()
                                 ->withErrors(['content' => 'Konten untuk tipe "generation_config_preset" harus berupa format JSON yang valid.'])
                                 ->withInput();
            }
        }

        try {
            PromptComponent::create($validated);
            return redirect()->route('admin.prompt-components.index')
                             ->with('success', 'Komponen Prompt berhasil dibuat.');
        } catch (\Exception $e) {
            Log::error('Error creating prompt component: ' . $e->getMessage());
            return redirect()->back()
                             ->with('error', 'Gagal membuat komponen prompt: ' . $e->getMessage())
                             ->withInput();
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    public function edit(PromptComponent $promptComponent) // Route model binding
    {
        Log::info('Editing PromptComponent:', $promptComponent->toArray()); // Alternatif untuk dd
        // Log::info(message: 'Editing PromptComponent:', ['id' => $promptComponent->id, 'name' => $promptComponent->component_name]); // Log data dasar
        // Log::info('Editing PromptComponent:', ['id' => $promptComponent->id, 'name' => $promptComponent->component_name]); // Log data dasar
        // $dataForVue = [
        //     'id' => $promptComponent->id,
        //     'component_name' => $promptComponent->component_name,
        //     'component_type' => $promptComponent->component_type,
        //     'content' => $promptComponent->content,
        //     'description' => $promptComponent->description,
        // ];
        // return Inertia::render('Admin/PromptTemplatesManage/Edit', [
        //     'promptTemplate' => $dataForVue,
        //     // Kirim juga errors jika ada dari redirect()->back()->withErrors() sebelumnya
        //     // 'errors' => session('errors') ? session('errors')->getBag('default')->getMessages() : (object) [],
        // ]);
        // dd($promptComponent, $this->validComponentTypes);
        return Inertia::render('Admin/PromptComponentsManage/Edit', [
            'promptComponent' => $promptComponent,
            'availableComponentTypes' => $this->validComponentTypes,
        ]);
    }

    public function update(Request $request, PromptComponent $promptComponent)
    {
        $validated = $request->validate([
            'component_name' => ['required','string','max:255',Rule::unique('prompt_components')->ignore($promptComponent->id)],
            'component_type' => ['required', 'string', Rule::in($this->validComponentTypes)],
            'content' => 'required|string',
            'description' => 'nullable|string',
        ]);

        if ($validated['component_type'] === 'generation_config_preset') {
            $jsonValidator = Validator::make(['content_json' => $validated['content']], ['content_json' => 'json']);
            if ($jsonValidator->fails()) {
                return redirect()->back()
                                 ->withErrors(['content' => 'Konten untuk tipe "generation_config_preset" harus berupa format JSON yang valid.'])
                                 ->withInput();
            }
        }

        try {
            $promptComponent->update($validated);
            return redirect()->route('admin.prompt-components.index')
                             ->with('success', 'Komponen Prompt berhasil diperbarui.');
        } catch (\Exception $e) {
            Log::error('Error updating prompt component: ' . $e->getMessage(), ['id' => $promptComponent->id]);
            return redirect()->back()
                             ->with('error', 'Gagal memperbarui komponen prompt: ' . $e->getMessage())
                             ->withInput();
        }
    }

    public function destroy(PromptComponent $promptComponent)
    {
        // Pertimbangkan validasi: jangan hapus jika sedang dipakai ConfiguredPromptComponentMapping
        // Ini memerlukan pengecekan relasi.
        // if ($promptComponent->configuredPromptMappings()->exists()) {
        //     return redirect()->route('admin.prompt-components.index')
        //                      ->with('error', 'Tidak dapat menghapus komponen ini karena sedang digunakan.');
        // }
        // Untuk relasi ini, Anda perlu mendefinisikan `configuredPromptMappings()` di model PromptComponent:
        // public function configuredPromptMappings() {
        //     return $this->hasMany(ConfiguredPromptComponentMapping::class, 'prompt_component_id');
        // }

        try {
            $componentName = $promptComponent->component_name;
            $promptComponent->delete();
            return redirect()->route('admin.prompt-components.index')
                             ->with('success', "Komponen Prompt \"{$componentName}\" berhasil dihapus.");
        } catch (\Exception $e) {
            Log::error('Error deleting prompt component: ' . $e->getMessage(), ['id' => $promptComponent->id]);
            return redirect()->route('admin.prompt-components.index')
                             ->with('error', 'Gagal menghapus komponen prompt. Terjadi kesalahan server.');
        }
    }
}
