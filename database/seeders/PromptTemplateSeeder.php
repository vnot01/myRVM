<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PromptTemplate;

class PromptTemplateSeeder extends Seeder
{
    public function run(): void
    {
        // Hapus template lama jika ada
        PromptTemplate::truncate();

        // Template 1: Default (mirip Strategi 1 kita)
        PromptTemplate::create([
            'name' => 'Default RVM Deposit Analysis v1',
            'description' => 'Standard analysis for RVM deposits, focusing on empty/filled status with examples.',
            'target_prompt' => 'plastic bottles (like mineral water, soda, tea, coffee bottles) or aluminum cans. Distinguish between different bottle types if possible.',
            'condition_prompt' => 'Determine if each item appears **EMPTY** (no visible liquid, debris, or significant residue) or **FILLED/CONTAMINATED** (contains visible liquid like water, visible trash like cigarette butts or sticks, or is significantly crushed/unsuitable). Be precise about emptiness.',
            'label_guidance' => "Use labels like: 'EMPTY mineral water bottle', 'EMPTY aluminum can', 'FILLED soda bottle - liquid visible', 'CONTAMINATED PET bottle - trash visible', 'CRUSHED aluminum can'.",
            'output_instructions' => "Output ONLY a valid JSON list (no extra text or markdown formatting like \`\`\`json ... \`\`\`) containing distinct items found, with a maximum of 5 items. Each entry in the list must be an object containing: 1. 'box_2d': The 2D bounding box ([ymin, xmin, ymax, xmax] scaled 0-1000). 2. 'label': The descriptive label. If no relevant items are found, output an empty JSON list [].",
            'generation_config' => json_encode([ // Simpan sebagai JSON string
                'candidateCount' => 1,
                'maxOutputTokens' => 1024,
                'temperature' => 0.3, // Suhu sedikit rendah
                'topP' => 0.95,
                'topK' => 40,
            ]),
            'is_active' => true, // Jadikan ini template aktif
        ]);

        // Template 2: Simplified Check (mirip Strategi 2 kita)
        PromptTemplate::create([
            'name' => 'Simplified Empty Check v1',
            'description' => 'Focuses only on EMPTY vs NOT EMPTY status.',
            'target_prompt' => 'plastic bottles (mineral water, soda, tea, etc.) or aluminum cans.',
            'condition_prompt' => 'Critically assess if the item is **EMPTY** (no visible contents or significant residue) or **NOT EMPTY** (has visible liquid, trash, or is crushed).',
            'label_guidance' => "Use labels like: 'EMPTY PET bottle', 'EMPTY aluminum can', 'NOT EMPTY PET bottle', 'NOT EMPTY aluminum can'. Specify bottle type only if clearly identifiable.",
            'output_instructions' => "Output ONLY a valid JSON list (no extra text or markdown formatting) of distinct items, max 5. Each object must have 'box_2d' ([ymin, xmin, ymax, xmax] scaled 0-1000) and 'label'. Output an empty JSON list [] if no items are found.",
            'generation_config' => null, // Gunakan default Gemini
            'is_active' => false,
        ]);

        // Tambahkan template lain jika perlu
    }
}