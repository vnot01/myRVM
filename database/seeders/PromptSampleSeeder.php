<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\PromptTemplate;
use App\Models\PromptComponent;
use App\Models\ConfiguredPrompt;
use App\Models\ConfiguredPromptComponentMapping;
// use App\Models\PromptPlaygroundSession; // Bisa juga di-seed jika perlu
use App\Models\User; // Untuk user_id di playground session

class PromptSampleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Buat Beberapa PromptTemplate Dasar
        // $template1 = PromptTemplate::create([
        //     'template_name' => 'Deteksi Item RVM Umum v1',
        //     'template_string' => "Deskripsi target: {{target_desc}}.\nKondisi item: {{item_condition}}.\nPilihan label: {{item_labels}}.\nFormat output: {{output_json_format}}.",
        //     'description' => 'Template umum untuk deteksi item di RVM.',
        //     'placeholders_defined' => json_encode(['target_desc', 'item_condition', 'item_labels', 'output_json_format']),
        // ]);
        $template1 = PromptTemplate::create([
            'template_name' => 'Deteksi Item RVM Umum v1',
            'template_string' => "Deskripsi target: {{target_desc}}.\nKondisi item: {{item_condition}}.\nPilihan label: {{item_labels}}.\nFormat output: {{output_json_format}}.",
            'description' => 'Template umum untuk deteksi item di RVM.',
            'placeholders_defined' => ['target_desc', 'item_condition', 'item_labels', 'output_json_format'], // INI BENAR (array PHP)
        ]);
        // Tambahkan template lain jika perlu

        // 2. Buat Beberapa PromptComponent
        // Target Descriptions
        $compTarget1 = PromptComponent::create(['component_name' => 'Target: Botol & Kaleng', 'component_type' => 'target_description', 'content' => 'Botol plastik (PET, HDPE) atau kaleng aluminium.']);
        $compTarget2 = PromptComponent::create(['component_name' => 'Target: Hanya Botol PET', 'component_type' => 'target_description', 'content' => 'Hanya botol plastik jenis PET (air mineral, soda).']);

        // Item Conditions
        $compCond1 = PromptComponent::create(['component_name' => 'Kondisi: Kosong & Bersih', 'component_type' => 'condition_details', 'content' => 'Pastikan item tampak kosong (tidak ada cairan/sisa signifikan) dan relatif bersih.']);
        $compCond2 = PromptComponent::create(['component_name' => 'Kondisi: Semua (Kosong/Isi)', 'component_type' => 'condition_details', 'content' => 'Analisis apakah item kosong atau masih berisi.']);

        // Item Labels
        $compLabels1 = PromptComponent::create(['component_name' => 'Label Set A (Umum)', 'component_type' => 'label_options', 'content' => 'PET_EMPTY, PET_FILLED, CAN_EMPTY, CAN_FILLED, TRASH, UNKNOWN']);
        $compLabels2 = PromptComponent::create(['component_name' => 'Label Set B (PET Saja)', 'component_type' => 'label_options', 'content' => 'PET_MINERAL_EMPTY, PET_SODA_EMPTY, PET_FILLED, PET_OTHER_TRASH']);

        // Output Formats
        $compFormat1 = PromptComponent::create(['component_name' => 'Output JSON v1 (type, valid, reason)', 'component_type' => 'output_format_definition', 'content' => '{"item_type": "LABEL", "is_valid": true/false, "rejection_reason": "ALASAN_JIKA_DITOLAK_ATAU_NULL"}']);

        // Generation Config Presets
        $compGenCfg1 = PromptComponent::create(['component_name' => 'GenConfig: Faktual Suhu Rendah', 'component_type' => 'generation_config_preset', 'content' => json_encode(['temperature' => 0.2, 'maxOutputTokens' => 256, 'topK' => 1, 'topP' => 0.9])]);
        $compGenCfg2 = PromptComponent::create(['component_name' => 'GenConfig: Kreatif Suhu Sedang', 'component_type' => 'generation_config_preset', 'content' => json_encode(['temperature' => 0.7, 'maxOutputTokens' => 512, 'topK' => 40, 'topP' => 0.95])]);


        // 3. Buat Beberapa ConfiguredPrompt (Contoh > 10)
        for ($i = 1; $i <= 12; $i++) {
            $isActive = ($i === 1); // Hanya yang pertama aktif
            $rootPromptId = null;
            $version = 1;

            $configuredPrompt = ConfiguredPrompt::create([
                'configured_prompt_name' => "Konfigurasi RVM Test V{$i}",
                'prompt_template_id' => $template1->id,
                'description' => "Deskripsi untuk Konfigurasi RVM Test V{$i}. Aktif: " . ($isActive ? 'Ya' : 'Tidak'),
                'generation_config_final' => ($i % 2 == 0) ? json_decode($compGenCfg1->content, true) : json_decode($compGenCfg2->content, true),
                'is_active' => $isActive,
                'version' => $version,
                'root_configured_prompt_id' => $rootPromptId, // Untuk versi pertama bisa null atau ID sendiri
            ]);

            if ($rootPromptId === null) { // Set root_id ke id sendiri untuk versi pertama
                $configuredPrompt->root_configured_prompt_id = $configuredPrompt->id;
                // $configuredPrompt->save(); // Tidak perlu save lagi jika di dalam create()
            }
            
            // Rakit full_prompt_text_generated (ini contoh sederhana, Anda perlu logika perakitan yang benar)
            $tempString = $template1->template_string;
            $targetComp = ($i % 3 == 0) ? $compTarget2 : $compTarget1;
            $condComp = ($i % 2 == 0) ? $compCond1 : $compCond2;
            $labelComp = $compLabels1;
            $formatComp = $compFormat1;

            $tempString = str_replace('{{target_desc}}', $targetComp->content, $tempString);
            $tempString = str_replace('{{item_condition}}', $condComp->content, $tempString);
            $tempString = str_replace('{{item_labels}}', $labelComp->content, $tempString);
            $tempString = str_replace('{{output_json_format}}', $formatComp->content, $tempString);
            $configuredPrompt->full_prompt_text_generated = $tempString;
            $configuredPrompt->save();


            // Buat Mapping
            ConfiguredPromptComponentMapping::create([
                'configured_prompt_id' => $configuredPrompt->id,
                'placeholder_in_template' => 'target_desc',
                'prompt_component_id' => $targetComp->id,
            ]);
            ConfiguredPromptComponentMapping::create([
                'configured_prompt_id' => $configuredPrompt->id,
                'placeholder_in_template' => 'item_condition',
                'prompt_component_id' => $condComp->id,
            ]);
             ConfiguredPromptComponentMapping::create([
                'configured_prompt_id' => $configuredPrompt->id,
                'placeholder_in_template' => 'item_labels',
                'prompt_component_id' => $labelComp->id,
            ]);
             ConfiguredPromptComponentMapping::create([
                'configured_prompt_id' => $configuredPrompt->id,
                'placeholder_in_template' => 'output_json_format',
                'prompt_component_id' => $formatComp->id,
            ]);
        }
    }
}
