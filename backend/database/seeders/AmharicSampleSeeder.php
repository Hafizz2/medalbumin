<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Note;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Subcategory;
use Illuminate\Database\Seeder;

/**
 * Sample Amharic content so the "Content language: አማርኛ" setting can be tried.
 * Editors review and replace these in the admin (each item has an Amharic tab).
 * Run: php artisan db:seed --class=AmharicSampleSeeder
 */
class AmharicSampleSeeder extends Seeder
{
    public function run(): void
    {
        $departments = [
            'Internal Medicine' => 'የውስጥ ደዌ ሕክምና',
            'Surgery' => 'ቀዶ ሕክምና',
            'Pediatrics & Child Health' => 'የሕፃናት ሕክምና እና ጤና',
            'Obstetrics & Gynecology' => 'የማህፀን እና የወሊድ ሕክምና',
            'Public Health' => 'የሕብረተሰብ ጤና',
            'Minor clinicals' => 'አነስተኛ ክሊኒካል ትምህርቶች',
        ];
        foreach (Department::all() as $d) {
            if ($am = $departments[$d->getTranslation('name', 'en')] ?? null) {
                $d->setTranslation('name', 'am', $am)->save();
            }
        }

        $subcategories = [
            'Cardiology' => 'የልብ ሕክምና (ካርዲዮሎጂ)',
            'Neurology' => 'የነርቭ ሕክምና (ኒውሮሎጂ)',
            'Pulmonology' => 'የሳንባ ሕክምና',
            'Gastroenterology & Hepatology' => 'የጨጓራ፣ አንጀት እና ጉበት ሕክምና',
            'Nephrology' => 'የኩላሊት ሕክምና',
            'Endocrinology' => 'የሆርሞን ሕክምና (ኢንዶክሪኖሎጂ)',
            'Hematology & Oncology' => 'የደም እና የካንሰር ሕክምና',
            'Infectious Diseases' => 'ተላላፊ በሽታዎች',
            'Rheumatology' => 'የመገጣጠሚያ ሕክምና (ሩማቶሎጂ)',
            'Clinical Pharmacology & Toxicology' => 'ክሊኒካል ፋርማኮሎጂ እና መርዝ',
            'General surgery' => 'ጠቅላላ ቀዶ ሕክምና',
            'Orthopedics' => 'የአጥንት ሕክምና (ኦርቶፔዲክስ)',
            'Urology' => 'የሽንት ቧንቧ ሕክምና (ዩሮሎጂ)',
            'Neurosurgery' => 'የነርቭ ቀዶ ሕክምና',
            'Neonatology' => 'የአራስ ሕፃናት ሕክምና',
            'Pediatric infections' => 'የሕፃናት ተላላፊ በሽታዎች',
            'Nutrition' => 'ሥርዓተ ምግብ',
            'IMNCI' => 'IMNCI (የሕፃናት በሽታዎች የተቀናጀ አያያዝ)',
            'Obstetrics' => 'የወሊድ ሕክምና',
            'Gynecology' => 'የማህፀን ሕክምና',
            'Epidemiology' => 'ኤፒዲሚዮሎጂ',
            'Biostatistics' => 'ባዮስታቲስቲክስ',
            'Health systems' => 'የጤና ሥርዓቶች',
            'Psychiatry' => 'የአእምሮ ጤና ሕክምና',
            'Ophthalmology' => 'የዓይን ሕክምና',
            'ENT' => 'የጆሮ፣ አፍንጫ እና ጉሮሮ ሕክምና',
            'Dermatology' => 'የቆዳ ሕክምና',
        ];
        foreach (Subcategory::all() as $s) {
            if ($am = $subcategories[$s->getTranslation('name', 'en')] ?? null) {
                $s->setTranslation('name', 'am', $am)->save();
            }
        }

        $notes = [
            'Stroke: localising the lesion in 5 minutes' => [
                'ስትሮክ፡ ጉዳቱ ያለበትን ቦታ በ5 ደቂቃ መለየት',
                '<p>መጀመሪያ ጉድለቱን ከደም ቧንቧው ጋር ያዛምዱ። <mark>ፊት እና ክንድ ከእግር በላይ ከተጎዱ MCA ን ያመለክታል</mark>፤ እግር ከክንድ በላይ ከተጎዳ ACA ን ያመለክታል።</p><table><thead><tr><th>የደም ቧንቧ</th><th>ዋና ዋና ምልክቶች</th></tr></thead><tbody><tr><td>MCA</td><td>በተቃራኒ ጎን የፊት እና የክንድ ድክመት፤ ዋናው ንፍቀ ክበብ ከሆነ የንግግር ችግር (aphasia)</td></tr><tr><td>ACA</td><td>በተቃራኒ ጎን የእግር ድክመት፣ ሽንት መቆጣጠር አለመቻል</td></tr><tr><td>PCA</td><td>በተቃራኒ ጎን homonymous hemianopia</td></tr><tr><td>የአንጎል ግንድ</td><td>የተሻገሩ ምልክቶች፡ በዚያው ጎን የክራኒያል ነርቭ፣ በተቃራኒ ጎን የሰውነት</td></tr></tbody></table><h3>ያስታውሱ</h3><p>UMN ጉዳት = tone እና reflexes ይጨምራሉ፣ Babinski ወደ ላይ። LMN ጉዳት = የጡንቻ መመንመን እና fasciculations።</p>',
            ],
            'Pre-eclampsia & eclampsia: MgSO₄ protocol' => [
                'ፕሪ-ኤክላምፕሲያ እና ኤክላምፕሲያ፡ የMgSO₄ አሰጣጥ',
                '<p>ከባድ ፕሪ-ኤክላምፕሲያ ወይም ኤክላምፕሲያ ላለባት ሴት ሁሉ ማግኒዚየም ሰልፌት ይስጡ።</p><h3>የPritchard አሰጣጥ</h3><ul><li>የመጀመሪያ መጠን፡ 4 ግ IV ቀስ ብሎ ከ5–10 ደቂቃ ውስጥ <strong>እና</strong> 10 ግ IM (በእያንዳንዱ ዳሌ 5 ግ)።</li><li>ቀጣይ መጠን፡ በየ4 ሰዓቱ 5 ግ IM፣ ዳሌዎችን እያፈራረቁ፣ ከወሊድ ወይም ከመጨረሻው ንዝረት በኋላ ለ24 ሰዓታት።</li></ul><h3>ከእያንዳንዱ መጠን በፊት ያረጋግጡ</h3><ul><li>የጉልበት reflex መኖሩን</li><li>የአተነፋፈስ መጠን &gt; 12/ደቂቃ</li><li>የሽንት መጠን &gt; 30 mL/ሰዓት</li></ul><p><mark>ማርከሻ፡ calcium gluconate 1 ግ (10 mL የ10%) IV ቀስ ብሎ።</mark></p>',
            ],
            'Heart failure drugs — what improves survival' => [
                'የልብ ድካም መድሃኒቶች — በሕይወት የመቆየትን ዕድል የሚጨምሩት',
                '<p>የejection fraction በቀነሰበት የልብ ድካም (HFrEF) አራት የመድሃኒት ዓይነቶች ሞትን ይቀንሳሉ፡</p><ol><li>ACE inhibitor / ARB / ARNI</li><li>Beta-blocker (bisoprolol፣ carvedilol፣ metoprolol succinate)</li><li>Mineralocorticoid antagonist (spironolactone)</li><li>SGLT2 inhibitor (dapagliflozin፣ empagliflozin)</li></ol><p>Loop diuretics የፈሳሽ መጠራቀምን ያቃልላሉ ነገር ግን በሕይወት የመቆየትን ዕድል <strong>አይጨምሩም</strong>።</p>',
            ],
        ];
        foreach (Note::all() as $n) {
            if ($am = $notes[$n->getTranslation('title', 'en')] ?? null) {
                $n->setTranslation('title', 'am', $am[0])->setTranslation('body', 'am', $am[1])->save();
            }
        }

        // Questions are matched by their English stem; options by key.
        $questions = [
            'A 58-year-old man has had crushing chest pain' => [
                'stem' => 'የ58 ዓመት ሰው ለ2 ሰዓታት የሚጨመድድ የደረት ሕመም አለበት። የልብ ምቱ 48/ደቂቃ ነው። ECG በII፣ III እና aVF ላይ ST elevation ያሳያል። በብዛት የተዘጋው የትኛው የደም ቧንቧ ነው?',
                'options' => ['A' => 'Left anterior descending', 'B' => 'Left circumflex', 'C' => 'የቀኝ ኮሮናሪ የደም ቧንቧ (RCA)', 'D' => 'Left main coronary', 'E' => 'Obtuse marginal ቅርንጫፍ'],
                'explanation' => 'በII፣ III እና aVF ላይ ያለ ST elevation የታችኛው ግድግዳ (inferior) MI ማለት ነው። የታችኛውን ግድግዳ በአብዛኛው ሰዎች የሚመግበው RCA ሲሆን RCA የAV nodeን ስለሚመግብ የልብ ምት መቀነሱን (bradycardia) ያስረዳል። የLAD መዘጋት በV1–V4፣ የcircumflex ደግሞ በI፣ aVL፣ V5–V6 ላይ ይታያል።',
            ],
            'Which drug class has been shown to reduce mortality in heart failure' => [
                'stem' => 'የejection fraction በቀነሰበት የልብ ድካም ሞትን እንደሚቀንስ የተረጋገጠው የትኛው የመድሃኒት ዓይነት ነው?',
                'options' => ['A' => 'Loop diuretics', 'B' => 'እንደ bisoprolol ወይም carvedilol ያሉ beta-blockers', 'C' => 'Digoxin', 'D' => 'እንደ verapamil ያሉ calcium channel blockers'],
                'explanation' => 'Beta-blockers (bisoprolol፣ carvedilol፣ metoprolol succinate)፣ ACE inhibitors/ARBs/ARNI፣ mineralocorticoid antagonists እና SGLT2 inhibitors በHFrEF ውስጥ በሕይወት የመቆየትን ዕድል ይጨምራሉ። Loop diuretics የፈሳሽ መጠራቀምን ያቃልላሉ እንጂ ሞትን አይቀንሱም፤ non-dihydropyridine CCBs ደግሞ HFrEFን ሊያባብሱ ይችላሉ።',
            ],
            'A 67-year-old woman has sudden weakness of the right face and arm' => [
                'stem' => 'የ67 ዓመት ሴት በድንገት የቀኝ ፊት እና ክንድ ድክመት አጋጥሟታል፤ እግሯ ብዙም አልተጎዳም፣ እና አቀላጥፋ መናገር አትችልም። የትኛው የደም ቧንቧ ሊሆን ይችላል?',
                'options' => ['A' => 'የግራ anterior cerebral artery', 'B' => 'የግራ middle cerebral artery', 'C' => 'የቀኝ middle cerebral artery', 'D' => 'የግራ posterior cerebral artery', 'E' => 'Basilar artery'],
                'explanation' => 'የፊት እና የክንድ ድክመት ከእግር በላይ መሆኑ middle cerebral arteryን ያመለክታል። የንግግር ችግር (aphasia) ዋናውን (በአብዛኛው የግራ) ንፍቀ ክበብ ያመለክታል፣ የቀኝ ጎን ምልክቶችም በግራ በኩል ላለ ጉዳት ተቃራኒ ናቸው። የACA ስትሮክ ከክንድ ይልቅ እግርን ይጎዳል።',
            ],
            'What is the drug of choice for absence seizures' => [
                'stem' => 'በ7 ዓመት ልጅ ላይ ለabsence seizures ተመራጭ መድሃኒቱ የትኛው ነው?',
                'options' => ['A' => 'Carbamazepine', 'B' => 'Phenytoin', 'C' => 'Ethosuximide', 'D' => 'Phenobarbital'],
                'explanation' => 'Ethosuximide ለተለመዱ absence seizures የመጀመሪያ ምርጫ ነው (valproate አማራጭ ነው)። Carbamazepine እና phenytoin absence seizuresን ሊያባብሱ ይችላሉ።',
            ],
            'What is the most common bacterial cause of community-acquired pneumonia' => [
                'stem' => 'በአዋቂዎች ላይ ከሆስፒታል ውጪ ለሚከሰት የሳንባ ምች (community-acquired pneumonia) በብዛት ምክንያት የሆነው ባክቴሪያ የትኛው ነው?',
                'options' => ['A' => 'Staphylococcus aureus', 'B' => 'Streptococcus pneumoniae', 'C' => 'Klebsiella pneumoniae', 'D' => 'Pseudomonas aeruginosa'],
                'explanation' => 'Streptococcus pneumoniae አሁንም በብዛት የሚገኘው የባክቴሪያ ምክንያት ነው። Klebsiella ከአልኮል ሱስ ጋር ይያያዛል፤ Pseudomonas ደግሞ ከሳንባ መዋቅራዊ በሽታ ወይም ከሆስፒታል ጋር።',
            ],
            'In the CURB-65 score for pneumonia' => [
                'stem' => 'ለሳንባ ምች በሚያገለግለው CURB-65 ውጤት ውስጥ ከሚከተሉት ውስጥ መስፈርት ያልሆነው የትኛው ነው?',
                'options' => ['A' => 'አዲስ ግራ መጋባት', 'B' => 'ዩሪያ > 7 mmol/L', 'C' => 'የአተነፋፈስ መጠን ≥ 30/ደቂቃ', 'D' => 'ትኩሳት ≥ 39 °C', 'E' => 'ዕድሜ ≥ 65 ዓመት'],
                'explanation' => 'CURB-65፡ Confusion (ግራ መጋባት)፣ Urea > 7 mmol/L፣ የአተነፋፈስ መጠን ≥ 30/ደቂቃ፣ የደም ግፊት (SBP < 90 ወይም DBP ≤ 60 mmHg)፣ ዕድሜ ≥ 65። ትኩሳት የውጤቱ አካል አይደለም።',
            ],
            'A 19-year-old with type 1 diabetes has vomiting' => [
                'stem' => 'ዓይነት 1 የስኳር በሽታ ያለበት የ19 ዓመት ወጣት ማስመለስ፣ ጥልቅ እና ፈጣን አተነፋፈስ እና 480 mg/dL የደም ስኳር አለው። IV ፈሳሽ ከተጀመረ በኋላ ኢንሱሊን ከመሰጠቱ በፊት መመርመር ያለበት የትኛው electrolyte ነው?',
                'options' => ['A' => 'ሶዲየም', 'B' => 'ፖታሲየም', 'C' => 'ካልሲየም', 'D' => 'ማግኒዚየም'],
                'explanation' => 'በDKA ውስጥ ኢንሱሊን ፖታሲየምን ወደ ሕዋሳት ያስገባል። የደም ፖታሲየም ከ3.3 mmol/L በታች ከሆነ አደገኛ hypokalaemia እና arrhythmia ለመከላከል ኢንሱሊን ከመጀመሩ በፊት ፖታሲየም መተካት አለበት።',
            ],
            'What is the first-line treatment for severe Plasmodium falciparum malaria' => [
                'stem' => 'በአዋቂዎች ላይ ለከባድ Plasmodium falciparum ወባ የመጀመሪያ ደረጃ ሕክምናው የትኛው ነው?',
                'options' => ['A' => 'Chloroquine በአፍ', 'B' => 'Artesunate በIV', 'C' => 'Artemether–lumefantrine በአፍ', 'D' => 'Primaquine በአፍ'],
                'explanation' => 'IV (ወይም IM) artesunate ለከባድ ወባ የመጀመሪያ ምርጫ ነው። ታካሚው በአፍ መውሰድ ሲችል እንደ artemether–lumefantrine ባለ artemisinin-based combination ሙሉ ሕክምናውን ያጠናቅቁ።',
            ],
        ];

        foreach (Question::with('options')->get() as $q) {
            $stem = $q->getTranslation('stem', 'en');
            $match = collect($questions)->first(fn ($v, $prefix) => str_starts_with($stem, $prefix));
            if (! $match) {
                continue;
            }
            $q->setTranslation('stem', 'am', $match['stem'])->setTranslation('explanation', 'am', $match['explanation']);
            $q->translation_status = array_merge($q->translation_status ?? [], ['am' => 'published']);
            $q->save();
            $q->options->each(function (QuestionOption $o) use ($match) {
                if (isset($match['options'][$o->key])) {
                    $o->setTranslation('text', 'am', $match['options'][$o->key])->save();
                }
            });
        }
    }
}
