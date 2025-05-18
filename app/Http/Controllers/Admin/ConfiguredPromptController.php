<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ConfiguredPrompt;
use App\Models\PromptTemplate; // Untuk memilih template dasar
use App\Models\PromptComponent; // Untuk memilih komponen
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Illuminate\Support\Str;

class ConfiguredPromptController extends Controller
{
    // GeminiVisionService akan diinject jika diperlukan untuk merakit/menguji prompt saat save
    // protected GeminiVisionService $geminiService;
    // public function __construct(GeminiVisionService $geminiService) { /* ... */ }


    public function index(Request $request)
    {
        $searchTerm = $request->query('search');
        $configuredPrompts = ConfiguredPrompt::query()
            ->with('template:id,template_name') // Eager load nama template dasar
            ->when($searchTerm, function ($query, $search) {
                $query->where('configured_prompt_name', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
            })
            ->orderBy('is_active', 'desc')
            ->orderBy('updated_at', 'desc')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Admin/ConfiguredPrompts/Index', [
            'configuredPrompts' => $configuredPrompts,
            'filters' => $request->only(['search']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/ConfiguredPrompts/Create', [
            'promptTemplates' => PromptTemplate::orderBy('template_name')->get(['id', 'template_name', 'template_string', 'placeholders_defined']),
            'promptComponents' => PromptComponent::orderBy('component_type')->orderBy('component_name')->get(['id', 'component_name', 'component_type', 'content']),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     * Ganti Request dengan Form Request jika Anda membuatnya: StoreConfiguredPromptRequest $request
     */
    public function store(Request $request) // Atau StoreConfiguredPromptRequest $request
    {
        // Otorisasi sudah ditangani oleh middleware rute 'role:Admin'

        // Validasi (Anda bisa pindahkan ini ke Form Request: StoreConfiguredPromptRequest)
        $validated = $request->validate([
            'configured_prompt_name' => 'required|string|max:255|unique:configured_prompts,configured_prompt_name',
            'prompt_template_id' => 'nullable|exists:prompt_templates,id',
            'description' => 'nullable|string',
            'full_prompt_text_generated' => 'required|string',
            'generation_config_final_json' => ['required', 'json'],
            'mappings' => 'nullable|array', // Array dari objek mapping
            'mappings.*.placeholder_in_template' => 'required_with:mappings|string',
            'mappings.*.prompt_component_id' => 'required_with:mappings|exists:prompt_components,id',
        ]);

        Log::info('[ConfiguredPromptStore] Validation passed. Data:', $validated);

        DB::beginTransaction();
        try {
            $generationConfigFinal = json_decode($validated['generation_config_final_json'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                // Kembalikan sebagai error validasi agar Inertia menanganinya
                return back()->withErrors(['generation_config_final_json' => 'Format JSON untuk Generation Config tidak valid.'])->withInput();
            }

            $configuredPrompt = ConfiguredPrompt::create([
                'configured_prompt_name' => $validated['configured_prompt_name'],
                'prompt_template_id' => $validated['prompt_template_id'] ?? null,
                'description' => $validated['description'] ?? null,
                'full_prompt_text_generated' => $validated['full_prompt_text_generated'],
                'generation_config_final' => $generationConfigFinal,
                'is_active' => false, // Default tidak aktif
                'version' => 1,
                // root_configured_prompt_id akan diisi setelah create
            ]);

            // Set root_configured_prompt_id ke id sendiri untuk versi pertama
            $configuredPrompt->root_configured_prompt_id = $configuredPrompt->id;
            $configuredPrompt->save(); // Simpan perubahan root_id

            Log::info('[ConfiguredPromptStore] ConfiguredPrompt created.', ['id' => $configuredPrompt->id]);


            // Simpan ConfiguredPrompt_Component_Mappings jika ada dan template_id dipilih
            if ($configuredPrompt->prompt_template_id && !empty($validated['mappings'])) {
                $mappingsToCreate = [];
                foreach ($validated['mappings'] as $mapping) {
                    $mappingsToCreate[] = [
                        // 'configured_prompt_id' => $configuredPrompt->id, // Tidak perlu jika menggunakan relasi
                        'placeholder_in_template' => $mapping['placeholder_in_template'],
                        'prompt_component_id' => $mapping['prompt_component_id'],
                    ];
                }
                if (!empty($mappingsToCreate)) {
                    $configuredPrompt->componentMappings()->createMany($mappingsToCreate);
                    Log::info('[ConfiguredPromptStore] Component mappings created.', ['count' => count($mappingsToCreate)]);
                }
            }

            DB::commit();
            return redirect()->route('admin.configured-prompts.index')->with('success', 'Konfigurasi prompt berhasil dibuat.');

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack(); // Tidak perlu jika validate() sudah di atas try-catch
            Log::warning('[ConfiguredPromptStore] ValidationException during store:', ['errors' => $e->errors()]);
            return redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating configured prompt: ' . $e->getMessage(), ['trace' => Str::limit($e->getTraceAsString(),1000)]);
            return redirect()->back()->with('error', 'Gagal membuat konfigurasi prompt: ' . $e->getMessage())->withInput();
        }
    }


    public function edit(ConfiguredPrompt $configuredPrompt) // Route model binding
    {
        // $configuredPrompt->load('template', 'componentMappings.component'); // Eager load relasi
        return Inertia::render('Admin/ConfiguredPrompts/Edit', [
            'configuredPrompt' => $configuredPrompt,
            'promptTemplates' => PromptTemplate::orderBy('template_name')->get(['id', 'template_name', 'template_string', 'placeholders_defined']),
            'promptComponents' => PromptComponent::orderBy('component_type')->orderBy('component_name')->get(['id', 'component_name', 'component_type', 'content']),
        ]);
    }

    public function update(Request $request, ConfiguredPrompt $configuredPrompt)
    {
        $validated = $request->validate([
            'configured_prompt_name' => ['required','string','max:255',Rule::unique('configured_prompts')->ignore($configuredPrompt->id)],
            'prompt_template_id' => 'nullable|exists:prompt_templates,id',
            'description' => 'nullable|string',
            'full_prompt_text_generated' => 'required|string',
            'generation_config_final_json' => ['required', 'json'],
            // 'mappings' => 'nullable|array',
        ]);

        DB::beginTransaction();
        try {
            $generationConfigFinal = json_decode($validated['generation_config_final_json'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \InvalidArgumentException('Format JSON untuk Generation Config tidak valid.');
            }

            // Untuk versioning sederhana: buat record baru sebagai revisi
            // $newVersion = ConfiguredPrompt::create([
            //     'configured_prompt_name' => $validated['configured_prompt_name'],
            //     'prompt_template_id' => $validated['prompt_template_id'] ?? null,
            //     'description' => $validated['description'] ?? null,
            //     'full_prompt_text_generated' => $validated['full_prompt_text_generated'],
            //     'generation_config_final' => $generationConfigFinal,
            //     'is_active' => false, // Versi baru tidak otomatis aktif
            //     'version' => $configuredPrompt->version + 1,
            //     'root_configured_prompt_id' => $configuredPrompt->root_configured_prompt_id ?? $configuredPrompt->id,
            // ]);
            // TODO: Salin mapping jika ada

            // Atau update in-place (lebih sederhana untuk sekarang)
            $configuredPrompt->update([
                'configured_prompt_name' => $validated['configured_prompt_name'],
                'prompt_template_id' => $validated['prompt_template_id'] ?? null,
                'description' => $validated['description'] ?? null,
                'full_prompt_text_generated' => $validated['full_prompt_text_generated'],
                'generation_config_final' => $generationConfigFinal,
            ]);
            // TODO: Update ConfiguredPrompt_Component_Mappings (hapus lama, buat baru)

            DB::commit();
            Cache::forget(config('services.google.active_prompt_cache_key', 'gemini_active_configured_prompt')); // Clear cache jika prompt aktif diubah
            return redirect()->route('admin.configured-prompts.index')->with('success', 'Konfigurasi prompt berhasil diperbarui.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating configured prompt: ' . $e->getMessage(), ['id' => $configuredPrompt->id, 'trace' => $e->getTraceAsString()]);
            return redirect()->back()->with('error', 'Gagal memperbarui konfigurasi prompt: ' . $e->getMessage())->withInput();
        }
    }

    // destroy, activate, testPrompt akan ditambahkan/disempurnakan
}