import DateTimePicker from '@react-native-community/datetimepicker';
import * as ImagePicker from 'expo-image-picker';
import { ReactNode, useState } from 'react';
import { Image, Platform, Pressable, TextInput, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { api, ApiError } from '../api/client';
import { useUniversities, useUpdateMe } from '../api/hooks';
import type { User } from '../api/types';
import { useAuth } from '../store/auth';
import { fonts, radius, useTheme } from '../theme';
import { Button } from './Button';
import { Icon } from './Icon';
import { PickerSheet } from './PickerSheet';
import { Text } from './Text';
import { Chip } from './ui';

const PROGRAMS = ['Medicine', 'Nursing', 'Pharmacy', 'Midwifery', 'Health officer', 'Anesthesia', 'Laboratory', 'Public health', 'Other'];
const YEARS = ['Year 1', 'Year 2', 'Year 3', 'Year 4', 'Year 5', 'Year 6', 'Intern', 'GP'];

/** Profile details used for onboarding and Profile → Edit. */
export function ProfileForm({ user, submitLabel, onSaved }: { user: User; submitLabel: string; onSaved: (u: User) => void }) {
  const { t } = useTranslation();
  const { c } = useTheme();
  const unis = useUniversities();
  const update = useUpdateMe();
  const setUser = useAuth((s) => s.setUser);

  const [name, setName] = useState(user.name ?? '');
  const [university, setUniversity] = useState<number | null>(user.university?.id ?? null);
  const [program, setProgram] = useState(user.program ?? 'Medicine');
  const [year, setYear] = useState(user.study_year ?? '');
  const [target, setTarget] = useState<User['target_exam']>(user.target_exam ?? 'exit');
  const [examDate, setExamDate] = useState<Date | null>(user.exam_date ? new Date(user.exam_date) : null);
  const [gender, setGender] = useState<User['gender']>(user.gender);
  const [avatar, setAvatar] = useState(user.avatar_url);
  const [picker, setPicker] = useState<'uni' | 'date' | null>(null);
  const [error, setError] = useState<string | null>(null);

  const uniName = unis.data?.data.find((u) => u.id === university)?.name;

  const pickAvatar = async () => {
    const res = await ImagePicker.launchImageLibraryAsync({ mediaTypes: ['images'], allowsEditing: true, aspect: [1, 1], quality: 0.8 });
    if (res.canceled || !res.assets[0]) return;
    const a = res.assets[0];
    const form = new FormData();
    form.append('avatar', { uri: a.uri, name: 'avatar.jpg', type: a.mimeType ?? 'image/jpeg' } as unknown as Blob);
    try {
      const u = await api<User>('/me/avatar', { form });
      setAvatar(u.avatar_url);
      setUser(u);
    } catch (e) {
      setError(e instanceof ApiError ? e.message : String(e));
    }
  };

  const submit = () => {
    setError(null);
    if (!name.trim()) return setError(t('profile.name_required', 'Please enter your name.'));
    update.mutate(
      {
        name: name.trim(),
        university_id: university,
        program,
        study_year: year || null,
        target_exam: target,
        exam_date: examDate ? examDate.toISOString().slice(0, 10) : null,
        gender,
      },
      {
        onSuccess: (u) => {
          setUser(u);
          onSaved(u);
        },
        onError: (e) => setError(e instanceof ApiError ? e.message : String(e)),
      },
    );
  };

  const programLabels: Record<string, string> = {
    Medicine: t('profile.program_medicine', 'Medicine'),
    Nursing: t('profile.program_nursing', 'Nursing'),
    Pharmacy: t('profile.program_pharmacy', 'Pharmacy'),
    Midwifery: t('profile.program_midwifery', 'Midwifery'),
    'Health officer': t('profile.program_health_officer', 'Health officer'),
    Anesthesia: t('profile.program_anesthesia', 'Anesthesia'),
    Laboratory: t('profile.program_laboratory', 'Laboratory'),
    'Public health': t('profile.program_public_health', 'Public health'),
    Other: t('profile.program_other', 'Other'),
  };
  const yearLabel = (y: string) => (y === 'Intern' ? t('profile.year_intern', 'Intern') : y === 'GP' ? t('profile.year_gp', 'GP') : t('profile.year_n', 'Year {n}', { n: y.replace('Year ', '') }));

  const targets: [NonNullable<User['target_exam']>, string][] = [
    ['exit', t('profile.target_exit', 'Exit exam')],
    ['qualification', t('profile.target_qualification', 'Qualification exam')],
    ['licensing', t('profile.target_licensing', 'Licensing exam')],
    ['residency', t('profile.target_residency', 'Residency exam')],
  ];

  return (
    <View style={{ gap: 18 }}>
      <Pressable onPress={pickAvatar} accessibilityRole="button" accessibilityLabel={t('profile.photo', 'Change photo')} style={{ alignSelf: 'center', alignItems: 'center', gap: 6 }}>
        {avatar ? (
          <Image source={{ uri: avatar }} style={{ width: 88, height: 88, borderRadius: 44 }} />
        ) : (
          <View style={{ width: 88, height: 88, borderRadius: 44, backgroundColor: c.skyBg, alignItems: 'center', justifyContent: 'center' }}>
            <Icon name="user" size={34} color={c.sky} />
          </View>
        )}
        <Text variant="smallStrong" color={c.teal}>
          {t('profile.photo', 'Change photo')}
        </Text>
      </Pressable>

      <Field label={t('profile.name', 'Full name')}>
        <TextInput value={name} onChangeText={setName} placeholder="Hana Tesfaye" placeholderTextColor={c.placeholder} autoComplete="name" textContentType="name" maxLength={120} style={inputStyle(c)} />
      </Field>

      <Field label={t('profile.university', 'University')}>
        <Pressable onPress={() => setPicker('uni')} accessibilityRole="button" style={[inputStyle(c), { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' }]}>
          <Text color={uniName ? c.text : c.placeholder}>{uniName ?? t('profile.choose_university', 'Choose your university')}</Text>
          <Icon name="chevronDown" size={16} color={c.muted} />
        </Pressable>
      </Field>

      <Field label={t('profile.program', 'Program')}>
        <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 8 }}>
          {PROGRAMS.map((p) => (
            <Chip key={p} label={programLabels[p] ?? p} selected={program === p} onPress={() => setProgram(p)} />
          ))}
        </View>
      </Field>

      <Field label={t('profile.year', 'Year of study')}>
        <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 8 }}>
          {YEARS.map((y) => (
            <Chip key={y} label={yearLabel(y)} selected={year === y} onPress={() => setYear(y)} />
          ))}
        </View>
      </Field>

      <Field label={t('profile.target_label', 'Preparing for')}>
        <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 8 }}>
          {targets.map(([id, label]) => (
            <Chip key={id} label={label} selected={target === id} onPress={() => setTarget(id)} />
          ))}
        </View>
      </Field>

      <Field label={t('profile.exam_date', 'Exam date (optional)')}>
        <Pressable onPress={() => setPicker('date')} accessibilityRole="button" style={[inputStyle(c), { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' }]}>
          <Text color={examDate ? c.text : c.placeholder}>{examDate ? examDate.toLocaleDateString(undefined, { day: 'numeric', month: 'long', year: 'numeric' }) : t('profile.choose_date', 'Choose date')}</Text>
          <Icon name="calendar" size={16} color={c.muted} />
        </Pressable>
        {picker === 'date' ? (
          <DateTimePicker
            value={examDate ?? new Date(Date.now() + 60 * 86400000)}
            mode="date"
            minimumDate={new Date(Date.now() + 86400000)}
            display={Platform.OS === 'ios' ? 'inline' : 'default'}
            onChange={(e, d) => {
              if (Platform.OS !== 'ios' || e.type === 'dismissed') setPicker(null);
              if (d && e.type !== 'dismissed') setExamDate(d);
            }}
          />
        ) : null}
      </Field>

      <Field label={t('profile.gender', 'Gender (optional — used for the official match-score bonus)')}>
        <View style={{ flexDirection: 'row', gap: 8 }}>
          <Chip label={t('profile.female', 'Female')} selected={gender === 'female'} onPress={() => setGender(gender === 'female' ? null : 'female')} />
          <Chip label={t('profile.male', 'Male')} selected={gender === 'male'} onPress={() => setGender(gender === 'male' ? null : 'male')} />
        </View>
      </Field>

      {error ? (
        <Text variant="small" color={c.danger}>
          {error}
        </Text>
      ) : null}
      <Button title={submitLabel} onPress={submit} loading={update.isPending} />

      <PickerSheet
        visible={picker === 'uni'}
        title={t('profile.university', 'University')}
        options={(unis.data?.data ?? []).map((u) => ({ id: String(u.id), label: u.name }))}
        value={university ? String(university) : null}
        onPick={(id) => setUniversity(Number(id))}
        onClose={() => setPicker(null)}
      />
    </View>
  );
}

function Field({ label, children }: { label: string; children: ReactNode }) {
  return (
    <View style={{ gap: 8 }}>
      <Text variant="smallStrong">{label}</Text>
      {children}
    </View>
  );
}

const inputStyle = (c: ReturnType<typeof useTheme>['c']) =>
  ({ height: 52, borderRadius: radius.md, borderWidth: 1, borderColor: c.field, backgroundColor: c.card, paddingHorizontal: 14, fontFamily: fonts.body, fontSize: 16, color: c.text, justifyContent: 'center' }) as const;
