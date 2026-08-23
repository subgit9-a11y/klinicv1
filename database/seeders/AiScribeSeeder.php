<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\AiFeature;
use App\Models\AiPrompt;
use App\Models\AiPromptVersion;
use Illuminate\Database\Seeder;

/**
 * Seeds the AI Scribe feature (key: ai_scribe) and its initial prompt
 * (SOAP extraction) so a fresh install has the feature wired end-to-end.
 */
class AiScribeSeeder extends Seeder
{
    public function run(): void
    {
        $prompt = AiPrompt::firstOrCreate(
            ['key' => 'ai_scribe'],
            ['name' => 'AI Scribe (SOAP extraction)', 'system' => 'GENERAL', 'is_active' => true]
        );

        AiPromptVersion::firstOrCreate(
            ['ai_prompt_id' => $prompt->id, 'version' => 1],
            [
                'system_prompt' => 'You are a medical scribe for Ayurveda/Siddha/Homeopathy clinics. Extract structured clinical notes from dictation text. Return ONLY valid JSON with keys subjective, objective, assessment, plan.',
                'user_prompt_template' => "Patient: {{patient_name}}\nSource: {{source}}\nDictation:\n{{dictation}}\n\nReturn JSON like {\"subjective\": \"...\", \"objective\": \"...\", \"assessment\": \"...\", \"plan\": \"...\"}.",
                'default_model' => 'gemini-2.0-flash',
                'created_by' => null,
            ]
        );

        AiFeature::firstOrCreate(
            ['key' => 'ai_scribe'],
            [
                'name' => 'AI Scribe',
                'description' => 'Dictation → transcription → SOAP/EMR draft → doctor approval.',
                'category' => 'CLINICAL',
                'default_prompt_key' => 'ai_scribe',
                'default_model' => 'gemini-2.0-flash',
                'is_active' => true,
            ]
        );
    }
}
