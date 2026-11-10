<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\FlashcardDeck;
use App\Models\Note;
use App\Models\Paper;
use App\Models\Question;
use App\Models\Subcategory;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Starter content so the app can be tried end-to-end before the content team
 * imports the real question bank. Run once:
 *   php artisan db:seed --class=DemoContentSeeder
 * Every item is marked "[Starter]" in the admin (paper description / deck title)
 * so editors can find, review and replace it. Medical content must still be
 * checked by the MedAlbumin medical team before launch.
 */
class DemoContentSeeder extends Seeder
{
    private ?int $authorId = null;

    public function run(): void
    {
        $this->authorId = User::query()->staff()->value('id');

        $paper = Paper::query()->firstOrCreate(
            ['name' => 'Starter practice paper', 'category' => 'exit'],
            ['year' => (int) date('Y'), 'description' => '[Starter] Mixed systems', 'is_premium' => false, 'status' => 'published'],
        );

        foreach ($this->questions() as $i => $q) {
            [$dept, $sub, $difficulty, $stem, $options, $correct, $explanation] = $q;
            $department = Department::query()->where('short_name', $dept)->firstOrFail();
            $subcategory = Subcategory::query()->where('department_id', $department->id)->where('name->en', $sub)->firstOrFail();

            if (Question::query()->where('stem->en', $stem)->exists()) {
                continue;
            }

            $question = Question::query()->create([
                'department_id' => $department->id,
                'subcategory_id' => $subcategory->id,
                'paper_id' => $paper->id,
                'paper_position' => $i + 1,
                'exam_year' => $paper->year,
                'difficulty' => $difficulty,
                'stem' => ['en' => $stem],
                'explanation' => ['en' => $explanation],
                'correct_option' => $correct,
                'status' => 'published',
                'is_free' => $i < 12,
                'author_id' => $this->authorId,
                'published_at' => now(),
            ]);
            foreach ($options as $k => $text) {
                $question->options()->create(['key' => $k, 'text' => ['en' => $text]]);
            }
        }

        $this->flashcards();
        $this->notes();
    }

    /** [dept short name, subcategory, difficulty, stem, options, correct, explanation] */
    private function questions(): array
    {
        return [
            ['Internal Med', 'Cardiology', 'moderate',
                'A 58-year-old man has had crushing chest pain for 2 hours. His pulse is 48/min. The ECG shows ST elevation in leads II, III and aVF. Which artery is most likely occluded?',
                ['A' => 'Left anterior descending', 'B' => 'Left circumflex', 'C' => 'Right coronary artery', 'D' => 'Left main coronary', 'E' => 'Obtuse marginal branch'], 'C',
                'ST elevation in II, III and aVF means an inferior MI. The inferior wall is supplied by the right coronary artery in most people, and the RCA also supplies the AV node — which explains the bradycardia. LAD occlusion shows in V1–V4; circumflex in I, aVL, V5–V6.'],
            ['Internal Med', 'Cardiology', 'easy',
                'Which drug class has been shown to reduce mortality in heart failure with reduced ejection fraction?',
                ['A' => 'Loop diuretics', 'B' => 'Beta-blockers such as bisoprolol or carvedilol', 'C' => 'Digoxin', 'D' => 'Calcium channel blockers such as verapamil'], 'B',
                'Beta-blockers (bisoprolol, carvedilol, metoprolol succinate), ACE inhibitors/ARBs/ARNI, mineralocorticoid antagonists and SGLT2 inhibitors improve survival in HFrEF. Loop diuretics relieve congestion but do not reduce mortality; non-dihydropyridine CCBs can worsen HFrEF.'],
            ['Internal Med', 'Neurology', 'moderate',
                'A 67-year-old woman has sudden weakness of the right face and arm, with the leg less affected, and she cannot speak fluently. Which artery is most likely involved?',
                ['A' => 'Left anterior cerebral artery', 'B' => 'Left middle cerebral artery', 'C' => 'Right middle cerebral artery', 'D' => 'Left posterior cerebral artery', 'E' => 'Basilar artery'], 'B',
                'Face and arm weakness worse than leg points to the middle cerebral artery. Aphasia means the dominant (usually left) hemisphere, and right-sided signs are contralateral to a left-sided lesion. ACA strokes affect the leg more than the arm.'],
            ['Internal Med', 'Neurology', 'easy',
                'What is the drug of choice for absence seizures in a 7-year-old child?',
                ['A' => 'Carbamazepine', 'B' => 'Phenytoin', 'C' => 'Ethosuximide', 'D' => 'Phenobarbital'], 'C',
                'Ethosuximide is first-line for typical absence seizures (valproate is an alternative). Carbamazepine and phenytoin can worsen absence seizures.'],
            ['Internal Med', 'Pulmonology', 'easy',
                'What is the most common bacterial cause of community-acquired pneumonia in adults?',
                ['A' => 'Staphylococcus aureus', 'B' => 'Streptococcus pneumoniae', 'C' => 'Klebsiella pneumoniae', 'D' => 'Pseudomonas aeruginosa'], 'B',
                'Streptococcus pneumoniae remains the most common bacterial cause of community-acquired pneumonia. Klebsiella is associated with alcohol use disorder; Pseudomonas with structural lung disease or hospital exposure.'],
            ['Internal Med', 'Pulmonology', 'moderate',
                'In the CURB-65 score for pneumonia, which of the following is NOT one of the criteria?',
                ['A' => 'New confusion', 'B' => 'Urea > 7 mmol/L', 'C' => 'Respiratory rate ≥ 30/min', 'D' => 'Fever ≥ 39 °C', 'E' => 'Age ≥ 65 years'], 'D',
                'CURB-65: Confusion, Urea > 7 mmol/L, Respiratory rate ≥ 30/min, Blood pressure (SBP < 90 or DBP ≤ 60 mmHg), age ≥ 65. Fever is not part of the score.'],
            ['Internal Med', 'Endocrinology', 'moderate',
                'A 19-year-old with type 1 diabetes has vomiting, deep rapid breathing and a blood glucose of 480 mg/dL. After starting IV fluids, which electrolyte must be checked before giving insulin?',
                ['A' => 'Sodium', 'B' => 'Potassium', 'C' => 'Calcium', 'D' => 'Magnesium'], 'B',
                'In DKA, insulin drives potassium into cells. If serum potassium is below about 3.3 mmol/L, potassium must be replaced before insulin is started to avoid dangerous hypokalaemia and arrhythmias.'],
            ['Internal Med', 'Infectious Diseases', 'moderate',
                'What is the first-line treatment for severe Plasmodium falciparum malaria in adults?',
                ['A' => 'Oral chloroquine', 'B' => 'IV artesunate', 'C' => 'Oral artemether–lumefantrine', 'D' => 'Oral primaquine'], 'B',
                'IV (or IM) artesunate is first-line for severe malaria. Once the patient can take oral medication, complete treatment with a full course of an artemisinin-based combination such as artemether–lumefantrine.'],
            ['Internal Med', 'Clinical Pharmacology & Toxicology', 'easy',
                'What is the antidote for paracetamol (acetaminophen) overdose?',
                ['A' => 'Naloxone', 'B' => 'Flumazenil', 'C' => 'N-acetylcysteine', 'D' => 'Atropine'], 'C',
                'N-acetylcysteine replenishes glutathione and is most effective within 8 hours of ingestion. If in doubt, start it.'],
            ['Internal Med', 'Clinical Pharmacology & Toxicology', 'moderate',
                'A farmer is brought in with pinpoint pupils, excessive salivation, sweating and bradycardia after spraying a pesticide. What is the first drug to give?',
                ['A' => 'Atropine', 'B' => 'Adrenaline', 'C' => 'Naloxone', 'D' => 'Physostigmine'], 'A',
                'This is organophosphate poisoning (cholinergic crisis). Atropine blocks the muscarinic effects and is titrated until secretions dry. Pralidoxime may be added to reactivate cholinesterase.'],
            ['Internal Med', 'Nephrology', 'moderate',
                'Which finding defines nephrotic syndrome in adults?',
                ['A' => 'Haematuria and red cell casts', 'B' => 'Proteinuria > 3.5 g/day with hypoalbuminaemia and oedema', 'C' => 'Proteinuria < 1 g/day with hypertension', 'D' => 'Rising creatinine with oliguria'], 'B',
                'Nephrotic syndrome: heavy proteinuria (> 3.5 g/24 h), hypoalbuminaemia and oedema, usually with hyperlipidaemia. Haematuria with red cell casts suggests nephritic syndrome.'],
            ['Internal Med', 'Hematology & Oncology', 'easy',
                'A woman has fatigue, a microcytic anaemia, low serum ferritin and high TIBC. What is the most likely diagnosis?',
                ['A' => 'Anaemia of chronic disease', 'B' => 'Iron deficiency anaemia', 'C' => 'Beta-thalassaemia trait', 'D' => 'Vitamin B12 deficiency'], 'B',
                'Low ferritin with high total iron-binding capacity is typical of iron deficiency. Anaemia of chronic disease has normal or high ferritin and low TIBC; B12 deficiency causes macrocytosis.'],
            ['Surgery', 'General surgery', 'easy',
                'A 22-year-old has pain that started around the umbilicus and moved to the right iliac fossa, with nausea and low-grade fever. What is the most likely diagnosis?',
                ['A' => 'Acute cholecystitis', 'B' => 'Acute appendicitis', 'C' => 'Perforated peptic ulcer', 'D' => 'Renal colic'], 'B',
                'Periumbilical pain migrating to the right iliac fossa with anorexia/nausea and low-grade fever is the classic presentation of acute appendicitis (the Alvarado score includes migration of pain).'],
            ['Surgery', 'General surgery', 'moderate',
                'After a road traffic accident, a patient has a blood pressure of 80/50 mmHg and pulse 130/min. What is the first step in management?',
                ['A' => 'CT scan of the abdomen', 'B' => 'Airway assessment with cervical spine protection', 'C' => 'Immediate laparotomy', 'D' => 'Give IV morphine'], 'B',
                'ATLS order is Airway with C-spine protection, Breathing, Circulation. Even in shock, the airway is secured first, followed by haemorrhage control and fluid/blood resuscitation.'],
            ['Surgery', 'Urology', 'easy',
                'An elderly man cannot pass urine for 12 hours and has a painful, palpable bladder. What is the immediate management?',
                ['A' => 'Start tamsulosin and send home', 'B' => 'Urethral catheterisation', 'C' => 'Transurethral resection of the prostate', 'D' => 'IV furosemide'], 'B',
                'Acute urinary retention is relieved immediately by urethral catheterisation (suprapubic if urethral fails). An alpha-blocker may then be started before a trial without catheter.'],
            ['Pediatrics', 'Neonatology', 'moderate',
                'A newborn is not breathing after drying and stimulation. What is the next step in neonatal resuscitation?',
                ['A' => 'Start chest compressions', 'B' => 'Give IV adrenaline', 'C' => 'Begin bag-and-mask ventilation within the first minute', 'D' => 'Suction the stomach'], 'C',
                'Helping Babies Breathe: if the baby is not breathing after drying and stimulation, start bag-and-mask ventilation within the “golden minute”. Compressions are only added if the heart rate stays below 60/min despite effective ventilation.'],
            ['Pediatrics', 'Nutrition', 'moderate',
                'A 2-year-old with severe acute malnutrition has bilateral pitting oedema but no complications and a good appetite. According to the national protocol, what is the main therapeutic food in outpatient care?',
                ['A' => 'F-75', 'B' => 'Ready-to-use therapeutic food (RUTF)', 'C' => 'Cow’s milk only', 'D' => 'IV dextrose'], 'B',
                'Children with uncomplicated SAM who pass the appetite test are treated as outpatients with RUTF. F-75 is used in the stabilisation phase for complicated SAM in hospital. Note: severe (+++) oedema requires inpatient care.'],
            ['Pediatrics', 'Pediatric infections', 'easy',
                'What is the recommended treatment for a child with some dehydration from acute watery diarrhoea?',
                ['A' => 'Plan A: home fluids only', 'B' => 'Plan B: ORS 75 mL/kg over 4 hours, plus zinc', 'C' => 'Plan C: IV fluids immediately', 'D' => 'Antibiotics and antidiarrhoeal drugs'], 'B',
                'Some dehydration = WHO Plan B: low-osmolarity ORS 75 mL/kg over 4 hours in the facility, with zinc for 10–14 days. Plan C (IV fluids) is for severe dehydration. Antidiarrhoeals are not recommended in children.'],
            ['OB/GYN', 'Obstetrics', 'moderate',
                'What is the drug of choice to prevent seizures in severe pre-eclampsia?',
                ['A' => 'Diazepam', 'B' => 'Phenytoin', 'C' => 'Magnesium sulfate', 'D' => 'Hydralazine'], 'C',
                'Magnesium sulfate is the drug of choice for preventing and treating eclamptic seizures. Monitor knee reflexes, respiratory rate (> 12/min) and urine output (> 30 mL/h); the antidote is calcium gluconate.'],
            ['OB/GYN', 'Obstetrics', 'moderate',
                'What is the most common cause of primary postpartum haemorrhage?',
                ['A' => 'Retained placenta', 'B' => 'Uterine atony', 'C' => 'Genital tract trauma', 'D' => 'Coagulopathy'], 'B',
                'Uterine atony (“tone”) causes most primary PPH — remember the 4 Ts: Tone, Trauma, Tissue, Thrombin. Active management of the third stage with oxytocin prevents it.'],
            ['OB/GYN', 'Gynecology', 'easy',
                'Which contraceptive method is the most effective at preventing pregnancy?',
                ['A' => 'Male condom', 'B' => 'Combined oral contraceptive pill', 'C' => 'Contraceptive implant', 'D' => 'Withdrawal method'], 'C',
                'Long-acting reversible contraceptives (implant and IUD) have failure rates below 1% per year with typical use, much lower than pills, condoms or withdrawal.'],
            ['Public Health', 'Epidemiology', 'moderate',
                'A new screening test correctly identifies 90 of 100 people who have the disease. What does this 90% describe?',
                ['A' => 'Specificity', 'B' => 'Sensitivity', 'C' => 'Positive predictive value', 'D' => 'Negative predictive value'], 'B',
                'Sensitivity = true positives / all with disease = 90/100. Specificity is about people without the disease; predictive values depend on prevalence.'],
            ['Public Health', 'Biostatistics', 'easy',
                'Which measure of central tendency is best for skewed data such as hospital length of stay?',
                ['A' => 'Mean', 'B' => 'Median', 'C' => 'Mode', 'D' => 'Range'], 'B',
                'The median is not pulled by extreme values, so it summarises skewed data better than the mean.'],
            ['Public Health', 'Epidemiology', 'moderate',
                'Researchers compare past smoking in people with lung cancer and in people without it. What type of study is this?',
                ['A' => 'Cohort study', 'B' => 'Case–control study', 'C' => 'Randomised controlled trial', 'D' => 'Cross-sectional study'], 'B',
                'Starting from outcome (cases vs controls) and looking back at exposure defines a case–control study. It reports an odds ratio.'],
            ['Minor', 'Ophthalmology', 'easy',
                'In the WHO SAFE strategy for trachoma, what does the “S” stand for?',
                ['A' => 'Screening', 'B' => 'Surgery for trichiasis', 'C' => 'Sanitation', 'D' => 'Steroids'], 'B',
                'SAFE: Surgery for trichiasis, Antibiotics (azithromycin), Facial cleanliness and Environmental improvement (water and sanitation).'],
            ['Minor', 'Psychiatry', 'moderate',
                'A man has had low mood, loss of interest, poor sleep and poor concentration for 3 weeks. What is the minimum duration needed to diagnose a major depressive episode?',
                ['A' => '1 week', 'B' => '2 weeks', 'C' => '1 month', 'D' => '6 months'], 'B',
                'DSM-5 requires at least 5 symptoms, including depressed mood or loss of interest, for at least 2 weeks.'],
        ];
    }

    private function flashcards(): void
    {
        $sub = Subcategory::query()->where('name->en', 'Clinical Pharmacology & Toxicology')->first();
        $deck = FlashcardDeck::query()->firstOrCreate(
            ['title->en' => 'Antidotes & toxicology'],
            ['title' => ['en' => 'Antidotes & toxicology'], 'department_id' => $sub?->department_id, 'subcategory_id' => $sub?->id, 'is_official' => true, 'status' => 'published'],
        );
        if ($deck->cards()->exists()) {
            return;
        }
        $cards = [
            ['Paracetamol overdose', 'What is the antidote, and when is it most effective?', 'N-acetylcysteine (NAC)', 'Replenishes glutathione. Best within 8 hours of ingestion; give it if in doubt.'],
            ['Organophosphate poisoning', 'First-line antidote pair?', 'Atropine + pralidoxime', 'Atropine blocks muscarinic effects; pralidoxime reactivates cholinesterase.'],
            ['Opioid overdose', 'Antidote and route?', 'Naloxone', 'IV, IM or intranasal. Short half-life — watch for re-sedation.'],
            ['Warfarin over-anticoagulation', 'Reversal agents?', 'Vitamin K ± FFP / PCC', 'Vitamin K for slow reversal; PCC or FFP when bleeding.'],
            ['Benzodiazepine overdose', 'Specific antidote?', 'Flumazenil', 'Use with care: can trigger seizures in chronic users.'],
            ['Iron poisoning', 'Chelating agent?', 'Deferoxamine', 'IV infusion; urine may turn “vin rosé” coloured.'],
            ['Heparin overdose', 'Reversal agent?', 'Protamine sulfate', '1 mg neutralises about 100 units of heparin.'],
            ['Methanol poisoning', 'Antidote?', 'Fomepizole or ethanol', 'Blocks alcohol dehydrogenase; consider dialysis.'],
            ['Magnesium sulfate toxicity', 'Antidote?', 'Calcium gluconate', '10 mL of 10% IV slowly. Signs: loss of knee reflexes, RR < 12.'],
        ];
        foreach ($cards as $i => [$front, $prompt, $back, $detail]) {
            $deck->cards()->create(['front' => ['en' => $front], 'prompt' => ['en' => $prompt], 'back' => ['en' => $back], 'detail' => ['en' => $detail], 'sort' => $i]);
        }
        $deck->refreshCount();
    }

    private function notes(): void
    {
        $notes = [
            ['Neurology', 'Stroke: localising the lesion in 5 minutes', 'all',
                '<p>Match the deficit to the artery first. <mark>Face and arm worse than leg points to the MCA</mark>; leg worse than arm points to the ACA.</p>'
                .'<table><thead><tr><th>Artery</th><th>Main findings</th></tr></thead><tbody>'
                .'<tr><td>MCA</td><td>Contralateral face + arm weakness; aphasia if dominant side</td></tr>'
                .'<tr><td>ACA</td><td>Contralateral leg weakness, urinary incontinence</td></tr>'
                .'<tr><td>PCA</td><td>Contralateral homonymous hemianopia</td></tr>'
                .'<tr><td>Brainstem</td><td>Crossed signs: ipsilateral cranial nerve, contralateral body</td></tr></tbody></table>'
                .'<h3>Remember</h3><p>UMN lesion = tone and reflexes up, Babinski up. LMN lesion = wasting and fasciculations.</p>'],
            ['Obstetrics', 'Pre-eclampsia & eclampsia: MgSO₄ protocol', 'all',
                '<p>Give magnesium sulfate to every woman with severe pre-eclampsia or eclampsia.</p>'
                .'<h3>Pritchard regimen</h3><ul><li>Loading: 4 g IV slowly over 5–10 minutes <strong>and</strong> 10 g IM (5 g in each buttock).</li><li>Maintenance: 5 g IM every 4 hours, alternating buttocks, for 24 hours after delivery or the last seizure.</li></ul>'
                .'<h3>Before each dose check</h3><ul><li>Knee reflexes present</li><li>Respiratory rate &gt; 12/min</li><li>Urine output &gt; 30 mL/hour</li></ul>'
                .'<p><mark>Antidote: calcium gluconate 1 g (10 mL of 10%) IV slowly.</mark></p>'],
            ['Cardiology', 'Heart failure drugs — what improves survival', 'premium',
                '<p>In heart failure with reduced ejection fraction, four drug classes reduce mortality:</p>'
                .'<ol><li>ACE inhibitor / ARB / ARNI</li><li>Beta-blocker (bisoprolol, carvedilol, metoprolol succinate)</li><li>Mineralocorticoid antagonist (spironolactone)</li><li>SGLT2 inhibitor (dapagliflozin, empagliflozin)</li></ol>'
                .'<p>Loop diuretics relieve congestion but <strong>do not</strong> improve survival.</p>'],
        ];
        foreach ($notes as [$subName, $title, $visibility, $body]) {
            $sub = Subcategory::query()->where('name->en', $subName)->first();
            if (! $sub || Note::query()->where('title->en', $title)->exists()) {
                continue;
            }
            Note::query()->create([
                'department_id' => $sub->department_id,
                'subcategory_id' => $sub->id,
                'title' => ['en' => $title],
                'body' => ['en' => $body],
                'read_minutes' => Note::estimateMinutes($body),
                'visibility' => $visibility,
                'status' => 'published',
                'author_id' => $this->authorId,
                'published_at' => now(),
            ]);
        }
    }
}
