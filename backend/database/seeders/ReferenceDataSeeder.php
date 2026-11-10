<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Drug;
use App\Models\Language;
use App\Models\LabValue;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\Specialty;
use App\Models\University;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Structural reference data the app needs to work. Safe to run again (idempotent).
 * Lab ranges and drug doses must be checked by the medical team against the
 * national treatment guideline before launch.
 */
class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->languages();
        $this->universities();
        $this->departments();
        $this->specialties();
        $this->plans();
        $this->labValues();
        $this->drugs();
        $this->settings();
    }

    /** English (default) and Amharic are live at launch; others are added later in Admin → Languages. */
    private function languages(): void
    {
        Language::query()->firstOrCreate(['code' => 'en'], ['name' => 'English', 'native_name' => 'English', 'direction' => 'ltr', 'is_active' => true, 'is_default' => true, 'sort' => 0, 'activated_at' => now()]);
        Language::query()->firstOrCreate(['code' => 'am'], ['name' => 'Amharic', 'native_name' => 'አማርኛ', 'direction' => 'ltr', 'is_active' => true, 'sort' => 1, 'activated_at' => now()]);
    }

    private function universities(): void
    {
        $list = [
            ['Addis Ababa University', 'AAU', 'Addis Ababa'],
            ['St. Paul’s Hospital Millennium Medical College', 'SPHMMC', 'Addis Ababa'],
            ['Jimma University', 'JU', 'Jimma'],
            ['University of Gondar', 'UoG', 'Gondar'],
            ['Bahir Dar University', 'BDU', 'Bahir Dar'],
            ['Hawassa University', 'HU', 'Hawassa'],
            ['Haramaya University', 'HrU', 'Harar'],
            ['Mekelle University', 'MU', 'Mekelle'],
            ['Arba Minch University', 'AMU', 'Arba Minch'],
            ['Wollo University', 'WU', 'Dessie'],
            ['Debre Markos University', 'DMU', 'Debre Markos'],
            ['Debre Tabor University', 'DTU', 'Debre Tabor'],
            ['Debre Berhan University', 'DBU', 'Debre Berhan'],
            ['Dilla University', 'DU', 'Dilla'],
            ['Wolaita Sodo University', 'WSU', 'Sodo'],
            ['Jigjiga University', 'JJU', 'Jigjiga'],
            ['Adigrat University', 'ADU', 'Adigrat'],
            ['Aksum University', 'AKU', 'Aksum'],
            ['Ambo University', 'AU', 'Ambo'],
            ['Wollega University', 'WoU', 'Nekemte'],
            ['Mizan-Tepi University', 'MTU', 'Mizan Aman'],
            ['Dire Dawa University', 'DDU', 'Dire Dawa'],
            ['Wachemo University', 'WCU', 'Hosaena'],
            ['Arsi University', 'ArU', 'Asella'],
            ['Madda Walabu University', 'MWU', 'Robe'],
            ['Bule Hora University', 'BHU', 'Bule Hora'],
            ['Adama Hospital Medical College', 'AHMC', 'Adama'],
            ['Myungsung Medical College (MCM)', 'MMC', 'Addis Ababa'],
            ['Hayat Medical College', 'HMC', 'Addis Ababa'],
            ['Africa Medical College', 'AMC', 'Addis Ababa'],
        ];
        foreach ($list as $i => [$name, $short, $city]) {
            University::query()->updateOrCreate(['name' => $name], ['short_name' => $short, 'city' => $city, 'sort' => $i]);
        }
    }

    /** Department → subcategory → topics, as in the Study and admin screens. */
    private function departments(): void
    {
        $tree = [
            ['Internal Medicine', 'Internal Med', 'steth', '#062A5E', '#FFFFFF', [
                'Cardiology' => ['Heart failure', 'Acute coronary syndrome', 'Arrhythmias', 'Valvular disease', 'Hypertension'],
                'Neurology' => ['Stroke', 'Epilepsy', 'Headache', 'Meningitis', 'Neuropathy', 'Parkinson’s disease'],
                'Pulmonology' => ['Asthma', 'COPD', 'Pneumonia', 'Tuberculosis', 'Pleural effusion'],
                'Gastroenterology & Hepatology' => ['Liver disease', 'GI bleeding', 'Inflammatory bowel disease', 'Pancreatitis'],
                'Nephrology' => ['Acute kidney injury', 'Chronic kidney disease', 'Nephrotic syndrome', 'Electrolytes'],
                'Endocrinology' => ['Diabetes mellitus', 'Thyroid', 'Adrenal', 'Diabetic ketoacidosis'],
                'Hematology & Oncology' => ['Anemia', 'Leukemia', 'Bleeding disorders'],
                'Infectious Diseases' => ['HIV', 'Malaria', 'Typhoid', 'Sepsis', 'Visceral leishmaniasis'],
                'Rheumatology' => ['Rheumatoid arthritis', 'SLE', 'Gout'],
                'Clinical Pharmacology & Toxicology' => ['Antidotes', 'Antimicrobials', 'Adverse drug reactions'],
            ]],
            ['Surgery', 'Surgery', 'blade', '#DDF3F5', '#017F8E', [
                'General surgery' => ['Acute abdomen', 'Hernia', 'Breast', 'Thyroid surgery', 'Trauma'],
                'Orthopedics' => ['Fractures', 'Bone infections', 'Spine'],
                'Urology' => ['Urinary retention', 'Stones', 'Prostate'],
                'Neurosurgery' => ['Head injury', 'Raised ICP'],
            ]],
            ['Pediatrics & Child Health', 'Pediatrics', 'baby', '#E4ECFB', '#2550A8', [
                'Neonatology' => ['Neonatal resuscitation', 'Neonatal jaundice', 'Neonatal sepsis'],
                'Pediatric infections' => ['Pneumonia in children', 'Diarrhoeal disease', 'Malaria in children'],
                'Nutrition' => ['Severe acute malnutrition', 'Micronutrients'],
                'IMNCI' => ['Danger signs', 'Growth monitoring', 'Immunization'],
            ]],
            ['Obstetrics & Gynecology', 'OB/GYN', 'female', '#F3E6F4', '#7A3B8E', [
                'Obstetrics' => ['Antenatal care', 'Hypertensive disorders of pregnancy', 'Obstetric hemorrhage', 'Labour'],
                'Gynecology' => ['Abnormal uterine bleeding', 'Family planning', 'Gynecologic oncology'],
            ]],
            ['Public Health', 'Public Health', 'globe', '#FFF3D6', '#7A5A12', [
                'Epidemiology' => ['Study designs', 'Outbreak investigation', 'Screening'],
                'Biostatistics' => ['Descriptive statistics', 'Hypothesis testing'],
                'Health systems' => ['Primary health care', 'Ethics & law'],
            ]],
            ['Minor clinicals', 'Minor', 'eye', '#E6EBF2', '#475569', [
                'Psychiatry' => ['Mood disorders', 'Psychosis', 'Substance use'],
                'Ophthalmology' => ['Red eye', 'Trachoma', 'Visual loss'],
                'ENT' => ['Otitis media', 'Epistaxis'],
                'Dermatology' => ['Skin infections', 'Eczema'],
            ]],
        ];

        foreach ($tree as $d => [$name, $short, $icon, $bg, $ink, $subs]) {
            $dept = Department::query()->updateOrCreate(['slug' => Str::slug($name)], [
                'name' => ['en' => $name], 'short_name' => $short, 'icon' => $icon, 'color_bg' => $bg, 'color_ink' => $ink, 'sort' => $d,
            ]);
            $s = 0;
            foreach ($subs as $subName => $topics) {
                $sub = $dept->subcategories()->updateOrCreate(['slug' => Str::slug($subName)], ['name' => ['en' => $subName], 'sort' => $s++]);
                foreach ($topics as $t => $topic) {
                    $sub->topics()->updateOrCreate(['name->en' => $topic], ['name' => ['en' => $topic], 'sort' => $t]);
                }
            }
        }
    }

    private function specialties(): void
    {
        $list = [
            ['IM', 'Internal Medicine', 'high'], ['PED', 'Pediatrics', 'medium'], ['SUR', 'General Surgery', 'high'],
            ['OBG', 'Obstetrics & Gynecology', 'high'], ['RAD', 'Radiology', 'very_high'], ['ANE', 'Anesthesiology', 'low'],
            ['PSY', 'Psychiatry', 'low'], ['EM', 'Emergency Medicine', 'medium'], ['ORT', 'Orthopedics', 'high'],
            ['OPH', 'Ophthalmology', 'high'], ['DER', 'Dermatology', 'very_high'], ['PAT', 'Pathology', 'low'],
            ['ENT', 'ENT', 'medium'], ['NSU', 'Neurosurgery', 'high'],
        ];
        foreach ($list as $i => [$code, $name, $level]) {
            Specialty::query()->updateOrCreate(['code' => $code], ['name' => ['en' => $name], 'competition' => $level, 'sort' => $i]);
        }
    }

    /**
     * Plan prices are placeholders ("[PRICE]" in the design) — set real prices in Admin → Payments.
     */
    private function plans(): void
    {
        $plans = [
            ['monthly', 'Monthly', 'Cancel anytime', null, 30, 0],
            ['semester', 'Semester', '6 months', 'Popular', 180, 1],
            ['yearly', 'Yearly', '12 months', 'Best value', 365, 2],
            ['exit-pack', 'Exit pack', 'Until exam day', null, 120, 3],
        ];
        $features = "Every question and past paper, with explanations\nUnlimited practice and timed mock exams\nGrand mocks with national rank and full reports\nAll official notes, flashcards and video lessons\nStudy on up to 2 phones";
        foreach ($plans as [$slug, $name, $note, $badge, $days, $sort]) {
            $plan = Plan::query()->firstOrCreate(['slug' => $slug], [
                'name' => ['en' => $name],
                'note' => ['en' => $note],
                'badge' => $badge ? ['en' => $badge] : null,
                'price' => 0,
                'duration_days' => $days,
                'sort' => $sort,
                'is_active' => false, // activated once a price is set
            ]);
            if (blank($plan->getTranslation('features', 'en', false))) {
                $plan->setTranslation('features', 'en', $features)->save();
            }
        }
    }

    private function labValues(): void
    {
        $rows = [
            ['Electrolytes', 'Sodium', '135–145', 'mmol/L'],
            ['Electrolytes', 'Potassium', '3.5–5.0', 'mmol/L'],
            ['Electrolytes', 'Chloride', '98–106', 'mmol/L'],
            ['Electrolytes', 'Bicarbonate', '22–28', 'mmol/L'],
            ['Electrolytes', 'Calcium (total)', '8.5–10.5', 'mg/dL'],
            ['Renal', 'Creatinine', '0.6–1.2', 'mg/dL'],
            ['Renal', 'Blood urea nitrogen', '7–20', 'mg/dL'],
            ['Metabolic', 'Fasting glucose', '70–100', 'mg/dL'],
            ['Metabolic', 'HbA1c', '< 5.7', '%'],
            ['Hematology', 'Hemoglobin (M)', '13.5–17.5', 'g/dL'],
            ['Hematology', 'Hemoglobin (F)', '12.0–15.5', 'g/dL'],
            ['Hematology', 'WBC', '4–11', '×10⁹/L'],
            ['Hematology', 'Platelets', '150–400', '×10⁹/L'],
            ['Hematology', 'MCV', '80–100', 'fL'],
            ['Liver', 'ALT', '7–56', 'U/L'],
            ['Liver', 'AST', '10–40', 'U/L'],
            ['Liver', 'Total bilirubin', '0.1–1.2', 'mg/dL'],
            ['Liver', 'Albumin', '3.5–5.0', 'g/dL'],
            ['Blood gas', 'pH (arterial)', '7.35–7.45', ''],
            ['Blood gas', 'PaCO₂', '35–45', 'mmHg'],
            ['Blood gas', 'PaO₂', '75–100', 'mmHg'],
        ];
        foreach ($rows as $i => [$cat, $name, $range, $unit]) {
            LabValue::query()->updateOrCreate(['name->en' => $name], ['category' => $cat, 'name' => ['en' => $name], 'range' => $range, 'unit' => $unit, 'sort' => $i]);
        }
    }

    private function drugs(): void
    {
        $rows = [
            ['Magnesium sulfate', 'OB/GYN · eclampsia', 'Pritchard: 4 g IV slowly + 10 g IM (5 g each buttock), then 5 g IM every 4 h.', 'Before each dose: knee reflex present, RR > 12, urine > 30 ml/h. Antidote: calcium gluconate.'],
            ['Paracetamol (children)', 'Pediatrics · analgesic', '15 mg/kg every 6 h. Maximum 60 mg/kg per day.', 'Overdose antidote: N-acetylcysteine.'],
            ['Naloxone', 'Emergency · opioid overdose', '0.4–2 mg IV, repeat every 2–3 min as needed.', 'Short-acting: watch for re-sedation.'],
        ];
        foreach ($rows as $i => [$name, $cat, $dose, $watch]) {
            Drug::query()->updateOrCreate(['name->en' => $name], ['name' => ['en' => $name], 'category' => ['en' => $cat], 'dose' => ['en' => $dose], 'watch' => ['en' => $watch], 'sort' => $i]);
        }
    }

    private function settings(): void
    {
        $defaults = [
            'max_devices' => 2,
            'guest_free_questions' => 20,
            'pass_mark' => 50,
            'seconds_per_question' => 72,
            'referral_reward_days' => 30,
            'referral_max_rewards' => 6,
            'bank_accounts' => [],
            'match.female_bonus_pct' => 5,
            'match.managerial_bonus_pct' => 5,
            'match.minimum_cutoff' => null,
            'match.max_specialties' => 2,
            'match.max_universities' => 3,
            'match.years' => 3,
            'match.audience' => 'final_year',
            'match.timeline' => ['Registration', 'Eligibility list', 'Entrance exam', 'Results & appeal', 'Match results'],
            'match.timeline_step' => 0,
        ];
        foreach ($defaults as $key => $value) {
            if (! Setting::query()->whereKey($key)->exists()) {
                Setting::put($key, $value);
            }
        }
    }
}
