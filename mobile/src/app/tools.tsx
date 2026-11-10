import { useState } from 'react';
import { Pressable, TextInput, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { useDrugs, useLabValues } from '../api/hooks';
import { ErrorState, Header, Screen, Skeleton } from '../components/Screen';
import { Text } from '../components/Text';
import { Card, Check, ChipRow, Chip, Segmented } from '../components/ui';
import { fonts, useTheme } from '../theme';

type Tab = 'lab' | 'calc' | 'drug';
type Calc = 'curb' | 'gcs' | 'bmi';

/** 24 · Lab values, calculators, drugs — "opens over any question". */
export default function Tools() {
  const { t } = useTranslation();
  const { c } = useTheme();
  const [tab, setTab] = useState<Tab>('lab');
  const labs = useLabValues();
  const drugs = useDrugs();

  return (
    <Screen>
      <Header title={t('tools.over_question', 'Opens over any question')} backIcon="close" />
      <Text variant="h1">{t('tools.title', 'Quick reference')}</Text>
      <Segmented<Tab>
        value={tab}
        onChange={setTab}
        options={[
          { id: 'lab', label: t('tools.labs', 'Lab values') },
          { id: 'calc', label: t('tools.calcs', 'Calculators') },
          { id: 'drug', label: t('tools.drugs', 'Drugs') },
        ]}
      />

      {tab === 'lab' ? (
        labs.isError && !labs.data ? (
          <ErrorState error={labs.error} onRetry={() => labs.refetch()} />
        ) : !labs.data ? (
          <Skeleton height={400} />
        ) : (
          labs.data.data.map((g) => (
            <View key={g.category} style={{ gap: 8 }}>
              <Text variant="label" muted>
                {g.category}
              </Text>
              <Card>
                {g.items.map((l, i) => (
                  <View key={l.name} style={{ flexDirection: 'row', alignItems: 'center', gap: 10, paddingHorizontal: 16, minHeight: 48, borderTopWidth: i ? 1 : 0, borderTopColor: c.divider }}>
                    <Text variant="bodyMedium" style={{ flex: 1 }}>
                      {l.name}
                    </Text>
                    <Text variant="mono" style={{ fontSize: 14 }}>
                      {l.range}
                    </Text>
                    <Text variant="caption" muted style={{ width: 64, textAlign: 'right' }}>
                      {l.unit}
                    </Text>
                  </View>
                ))}
              </Card>
            </View>
          ))
        )
      ) : null}

      {tab === 'calc' ? <Calculators /> : null}

      {tab === 'drug' ? (
        drugs.isError && !drugs.data ? (
          <ErrorState error={drugs.error} onRetry={() => drugs.refetch()} />
        ) : !drugs.data ? (
          <Skeleton height={400} />
        ) : (
          <>
            {drugs.data.data.map((d) => (
              <Card key={d.id} style={{ padding: 16, gap: 8 }}>
                <View style={{ gap: 2 }}>
                  <Text variant="bodyStrong">{d.name}</Text>
                  <Text variant="caption" muted>
                    {d.category}
                  </Text>
                </View>
                <Text variant="small">{d.dose}</Text>
                {d.watch ? (
                  <View style={{ backgroundColor: c.warnBg, borderRadius: 12, padding: 10 }}>
                    <Text variant="caption" color={c.warn}>
                      {d.watch}
                    </Text>
                  </View>
                ) : null}
              </Card>
            ))}
            <Text variant="caption" muted align="center">
              {drugs.data.disclaimer}
            </Text>
          </>
        )
      ) : null}
    </Screen>
  );
}

function Calculators() {
  const { t } = useTranslation();
  const { c } = useTheme();
  const [calc, setCalc] = useState<Calc>('curb');
  return (
    <View style={{ gap: 12 }}>
      <ChipRow>
        <Chip label="CURB-65" selected={calc === 'curb'} onPress={() => setCalc('curb')} />
        <Chip label={t('tools.gcs', 'GCS')} selected={calc === 'gcs'} onPress={() => setCalc('gcs')} />
        <Chip label="BMI" selected={calc === 'bmi'} onPress={() => setCalc('bmi')} />
      </ChipRow>
      {calc === 'curb' ? <Curb65 /> : calc === 'gcs' ? <Gcs /> : <Bmi />}
      <Text variant="caption" muted align="center">
        {t('tools.calc_note', 'For exam study, not patient care.')}
      </Text>
      <View style={{ height: 1, backgroundColor: c.divider }} />
    </View>
  );
}

function ScoreCard({ score, risk, advice, tone }: { score: string; risk: string; advice: string; tone: 'ok' | 'warn' | 'danger' }) {
  const { c } = useTheme();
  const color = tone === 'ok' ? c.accent : tone === 'warn' ? '#FBBF77' : '#F28C5B';
  return (
    <View style={{ backgroundColor: '#062A5E', borderRadius: 20, padding: 16, flexDirection: 'row', alignItems: 'center', gap: 16 }} accessibilityLiveRegion="polite">
      <Text style={{ fontFamily: fonts.displayBold, fontSize: 44 }} color={color}>
        {score}
      </Text>
      <View style={{ flex: 1, gap: 2 }}>
        <Text style={{ fontFamily: fonts.display, fontSize: 18 }} color="#F3F6FA">
          {risk}
        </Text>
        <Text variant="small" color="#A9BBD8">
          {advice}
        </Text>
      </View>
    </View>
  );
}

function Curb65() {
  const { t } = useTranslation();
  const { c } = useTheme();
  const items: [string, string, string][] = [
    ['c', 'C', t('tools.curb_c', 'New confusion')],
    ['u', 'U', t('tools.curb_u', 'Urea > 7 mmol/L')],
    ['r', 'R', t('tools.curb_r', 'Respiratory rate ≥ 30/min')],
    ['b', 'B', t('tools.curb_b', 'SBP < 90 or DBP ≤ 60 mmHg')],
    ['a', '65', t('tools.curb_a', 'Age ≥ 65 years')],
  ];
  const [on, setOn] = useState<Record<string, boolean>>({});
  const score = Object.values(on).filter(Boolean).length;
  return (
    <>
      <Card>
        {items.map(([id, k, label], i) => (
          <Pressable key={id} accessibilityRole="checkbox" accessibilityState={{ checked: !!on[id] }} onPress={() => setOn({ ...on, [id]: !on[id] })} style={{ minHeight: 50, paddingHorizontal: 14, flexDirection: 'row', alignItems: 'center', gap: 12, borderTopWidth: i ? 1 : 0, borderTopColor: c.divider }}>
            <Check checked={!!on[id]} />
            <Text variant="mono" style={{ width: 26 }}>
              {k}
            </Text>
            <Text variant="small" style={{ flex: 1 }}>
              {label}
            </Text>
          </Pressable>
        ))}
      </Card>
      <ScoreCard
        score={String(score)}
        tone={score <= 1 ? 'ok' : score === 2 ? 'warn' : 'danger'}
        risk={score <= 1 ? t('tools.low_risk', 'Low risk') : score === 2 ? t('tools.moderate_risk', 'Moderate risk') : t('tools.high_risk', 'High risk')}
        advice={score <= 1 ? t('tools.curb_low', 'Consider outpatient treatment') : score === 2 ? t('tools.curb_mod', 'Consider hospital admission') : t('tools.curb_high', 'Manage as severe pneumonia; assess for ICU')}
      />
    </>
  );
}

function Gcs() {
  const { t } = useTranslation();
  const { c } = useTheme();
  const groups: [string, string, string[]][] = [
    ['e', t('tools.gcs_eye', 'Eye opening'), [t('tools.gcs_e1', 'None'), t('tools.gcs_e2', 'To pain'), t('tools.gcs_e3', 'To voice'), t('tools.gcs_e4', 'Spontaneous')]],
    ['v', t('tools.gcs_verbal', 'Verbal'), [t('tools.gcs_v1', 'None'), t('tools.gcs_v2', 'Sounds'), t('tools.gcs_v3', 'Words'), t('tools.gcs_v4', 'Confused'), t('tools.gcs_v5', 'Oriented')]],
    ['m', t('tools.gcs_motor', 'Motor'), [t('tools.gcs_m1', 'None'), t('tools.gcs_m2', 'Extension'), t('tools.gcs_m3', 'Abnormal flexion'), t('tools.gcs_m4', 'Withdraws'), t('tools.gcs_m5', 'Localises'), t('tools.gcs_m6', 'Obeys')]],
  ];
  const [v, setV] = useState<Record<string, number>>({ e: 4, v: 5, m: 6 });
  const score = v.e + v.v + v.m;
  return (
    <>
      {groups.map(([id, label, opts]) => (
        <View key={id} style={{ gap: 6 }}>
          <Text variant="smallStrong">{label}</Text>
          <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 6 }}>
            {opts.map((o, i) => (
              <Pressable key={o} accessibilityRole="radio" accessibilityState={{ checked: v[id] === i + 1 }} onPress={() => setV({ ...v, [id]: i + 1 })} style={{ paddingHorizontal: 10, minHeight: 36, justifyContent: 'center', borderRadius: 99, borderWidth: 1, borderColor: v[id] === i + 1 ? c.navy : c.field, backgroundColor: v[id] === i + 1 ? c.navy : c.card }}>
                <Text variant="caption" color={v[id] === i + 1 ? c.onNavy : c.text}>
                  {i + 1} · {o}
                </Text>
              </Pressable>
            ))}
          </View>
        </View>
      ))}
      <ScoreCard
        score={String(score)}
        tone={score >= 13 ? 'ok' : score >= 9 ? 'warn' : 'danger'}
        risk={score >= 13 ? t('tools.gcs_mild', 'Mild') : score >= 9 ? t('tools.gcs_moderate', 'Moderate') : t('tools.gcs_severe', 'Severe')}
        advice={`E${v.e} V${v.v} M${v.m}${score <= 8 ? ` · ${t('tools.gcs_airway', 'Consider airway protection')}` : ''}`}
      />
    </>
  );
}

function Bmi() {
  const { t } = useTranslation();
  const { c } = useTheme();
  const [w, setW] = useState('');
  const [h, setH] = useState('');
  const bmi = Number(w) > 0 && Number(h) > 0 ? Number(w) / (Number(h) / 100) ** 2 : null;
  const band = bmi === null ? null : bmi < 18.5 ? t('tools.bmi_under', 'Underweight') : bmi < 25 ? t('tools.bmi_normal', 'Normal') : bmi < 30 ? t('tools.bmi_over', 'Overweight') : t('tools.bmi_obese', 'Obese');
  const input = { flex: 1, height: 52, borderRadius: 14, borderWidth: 1, borderColor: c.field, backgroundColor: c.card, paddingHorizontal: 14, fontFamily: fonts.mono, fontSize: 17, color: c.text } as const;
  return (
    <>
      <View style={{ flexDirection: 'row', gap: 10 }}>
        <TextInput value={w} onChangeText={setW} keyboardType="decimal-pad" placeholder={t('tools.weight', 'Weight (kg)')} placeholderTextColor={c.placeholder} style={input} accessibilityLabel={t('tools.weight', 'Weight (kg)')} />
        <TextInput value={h} onChangeText={setH} keyboardType="decimal-pad" placeholder={t('tools.height', 'Height (cm)')} placeholderTextColor={c.placeholder} style={input} accessibilityLabel={t('tools.height', 'Height (cm)')} />
      </View>
      {bmi !== null ? <ScoreCard score={bmi.toFixed(1)} tone={bmi >= 18.5 && bmi < 25 ? 'ok' : bmi < 30 ? 'warn' : 'danger'} risk={band ?? ''} advice="kg/m²" /> : null}
    </>
  );
}
