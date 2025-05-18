<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ConfiguredPrompt;
use App\Models\PromptTemplate;
use App\Models\PromptComponent;
use App\Services\GeminiVisionService; // Pastikan ini di-import
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

    protected GeminiVisionService $geminiService;

    public function __construct(GeminiVisionService $geminiService)
    {
        $this->geminiService = $geminiService;
    }


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

    /**
     * Test a given prompt configuration with an image.
     * Metode ini akan dipanggil oleh UI "Test Prompt Cepat".
     */
    public function testPrompt(Request $request)
    {
        // Otorisasi dasar (rute sudah dilindungi role:Admin)
        // if (!Auth::check() || Auth::user()->role !== 'Admin') {
        //     return response()->json(['error' => 'Unauthorized'], 403);
        // }

        info('[TestPrompt] Received request to test prompt with image and prompt.');
        $validator = Validator::make($request->all(), [
            'image' => 'required|image|mimes:jpeg,png,jpg|max:5120',
            'full_prompt' => 'required|string', // Menerima full_prompt yang sudah dirakit
            'generation_config_json' => ['required', 'json'], // Menerima JSON string
        ]);

        if ($validator->fails()) {
            Log::warning('[TestPrompt] Validation failed:', $validator->errors()->toArray());
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $imageFile = $request->file('image');
        $fullPrompt = $request->input('full_prompt');
        $generationConfigJson = $request->input('generation_config_json'); // Ini adalah STRING JSON
        $generationConfig = null;
        // info('[TestPrompt] String JSON Config:', ['config_string' => $generationConfigJson]);
        // Log input dengan benar
        // info('[TestPrompt] Received raw inputs:', [
        //     'has_image' => $request->hasFile('image'),
        //     'full_prompt_length' => strlen($fullPrompt),
        //     'generation_config_json_string' => $generationConfigJson // Log string JSON sebagai bagian dari array konteks
        // ]);

        if ($generationConfigJson) {
            $decodedConfig = json_decode($generationConfigJson, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decodedConfig)) {
                $generationConfig = $decodedConfig;
                // info('[TestPrompt] Parsed generation_config:', ['json_string' => $generationConfigJson]); // $generationConfig sekarang array, jadi ini benar
            } else {
                Log::warning('[TestPrompt] Invalid JSON for generation_config', ['json_string' => $generationConfigJson]);
                // return response()->json(['error' => 'Format JSON untuk Generation Config tidak valid.'], 400); // Pertimbangkan ini
            }
        }

        info('[TestPrompt] Testing with received prompt and config.', [
            'prompt_length' => strlen($fullPrompt),
            'has_gen_config' => !is_null($generationConfig)
        ]);

        try {
            $results = $this->geminiService->analyzeWithCustomPromptAndConfig(
                $imageFile,
                $fullPrompt,
                $generationConfig // Ini sudah array atau null
            );

            // info('[TestPrompt] : curl_error = ',$results['curl_error']);
            // info('[TestPrompt] : http_code = ',$results['http_code']);
            // info('[TestPrompt] : parsed_data = ',$results['parsed_data']);
            // info('[TestPrompt] : raw_text = ',$results['raw_text']);
            // Periksa hasil dari service
            if (isset($results['curl_error']) && $results['curl_error']) {
                 return response()->json(['error' => 'Gagal menghubungi Gemini API: ' . $results['curl_error']], 500);
            }
            if ($results['http_code'] !== 200) {
                 return response()->json(['error' => 'Gemini API merespons dengan error: ' . $results['http_code'], 'details' => $results['parsed_data'] ?? $results['raw_text']], $results['http_code']);
            }

            return response()->json([
                'success' => true,
                'gemini_raw_response_text' => $results['raw_text'],
                'parsed_results' => $results['parsed_data'],
                'full_prompt_sent' => $fullPrompt, // Untuk debug di frontend
                'generation_config_used' => $generationConfig // Untuk debug di frontend
            ]);

        } catch (\Exception $e) {
            Log::error('[TestPrompt] Exception during Gemini API call: ' . $e->getMessage(), ['trace' => Str::limit($e->getTraceAsString(), 1500)]);
            return response()->json(['error' => 'Terjadi kesalahan internal server saat pengujian: ' . $e->getMessage()], 500);
        }
    }
    
    // destroy, activate, testPrompt akan ditambahkan/disempurnakan
}