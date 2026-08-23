<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\AiFeature;
use App\Models\AiPrompt;
use App\Models\AiPromptVersion;
use Illuminate\Database\Seeder;

/**
 * Seeds the dedicated AI summary products (patient/lab/document/treatment/
 * IPD summaries + follow-up assistant) with their initial prompt versions so
 * a fresh install has every feature wired end-to-end. Idempotent.
 */
class AiSummarySeeder extends Seeder
{
    /** @var array<string, array{name:string, description:string, system:string, template:string}> */
    private const FEATURES = [
        'patient_summary' => [
            'name' => 'Patient Summary',
            'description' => 'Whole-chart summary: demographics, recent consultations, assessments.',
            'system' => 'You are a clinical summarizer for Ayurveda/Siddha/Homeopathy clinics. Produce a concise, factual patient chart summary. Never invent findings; only use the provided context.',
            'template' => "Patient: {{patient_name}} ({{patient_meta}})\nRecent consultations:\n{{history}}\n\nWrite a concise chart summary (max 200 words) with sections: Overview, Recent course, Active concerns.",
        ],
        'followup_assistant' => [
            'name' => 'Follow-up Assistant',
            'description' => 'Suggests a follow-up plan from pending/missed follow-ups and the latest consultation.',
            'system' => 'You are a clinical follow-up planner. Suggest a practical follow-up schedule and what to review at each visit. Only use the provided context; mark suggestions as drafts for the treating doctor.',
            'template' => "Patient: {{patient_name}}\nLast consultation: {{last_consultation}}\nExisting follow-ups:\n{{followups}}\n\nPropose a follow-up plan (what to review, suggested intervals, red flags to watch).",
        ],
        'lab_summary' => [
            'name' => 'Lab Summary',
            'description' => 'Interprets structured investigation results and OCR report text.',
            'system' => 'You are a laboratory report summarizer. Summarize results, flag abnormal values against their reference ranges, and note anything requiring clinical attention. Never diagnose; present findings for the doctor.',
            'template' => "Test: {{test_name}} ({{category}})\nStructured results:\n{{results}}\nReport text:\n{{report_text}}\n\nSummarize: key findings, abnormal values, items needing attention.",
        ],
        'document_summary' => [
            'name' => 'Document Summary',
            'description' => 'Summarizes OCR-extracted text from an uploaded document.',
            'system' => 'You are a medical document summarizer. Summarize the extracted document text faithfully. If the text is incomplete or garbled, say so.',
            'template' => "Document: {{document_name}} ({{document_type}})\nExtracted text:\n{{ocr_text}}\n\nProvide a short structured summary of this document.",
        ],
        'treatment_summary' => [
            'name' => 'Treatment Summary',
            'description' => 'Summarizes a treatment plan and session progress.',
            'system' => 'You are a treatment progress summarizer for Ayurveda/Siddha therapy plans. Summarize the plan and session attendance/progress.',
            'template' => "Patient: {{patient_name}}\nPlan: {{plan}}\nSessions:\n{{sessions}}\n\nSummarize treatment progress and note gaps or missed sessions.",
        ],
        'ipd_summary' => [
            'name' => 'IPD Summary',
            'description' => 'Summarizes the course of an IPD admission from daily notes.',
            'system' => 'You are an inpatient course summarizer. Summarize the admission course from daily notes: presentation, progress, current status. Factual only.',
            'template' => "Patient: {{patient_name}}\nAdmission: {{admission}}\nDaily notes:\n{{daily_notes}}\n\nWrite a course-in-hospital summary suitable for a discharge summary draft.",
        ],
    ];

    public function run(): void
    {
        foreach (self::FEATURES as $key => $definition) {
            $prompt = AiPrompt::firstOrCreate(
                ['key' => $key],
                ['name' => $definition['name'], 'system' => 'GENERAL', 'is_active' => true]
            );

            AiPromptVersion::firstOrCreate(
                ['ai_prompt_id' => $prompt->id, 'version' => 1],
                [
                    'system_prompt' => $definition['system'],
                    'user_prompt_template' => $definition['template'],
                    'default_model' => 'gemini-2.0-flash',
                    'created_by' => null,
                ]
            );

            AiFeature::firstOrCreate(
                ['key' => $key],
                [
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'category' => 'CLINICAL',
                    'default_prompt_key' => $key,
                    'default_model' => 'gemini-2.0-flash',
                    'is_active' => true,
                ]
            );
        }
    }
}
