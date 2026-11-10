import * as FileSystem from 'expo-file-system/legacy';
import { useRouter } from 'expo-router';
import * as Sharing from 'expo-sharing';
import { useState } from 'react';
import { Alert, Pressable, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { API_URL } from '../../api/client';
import { useChoices, useSaveChoices, useUpdateMatchProfile } from '../../api/hooks';
import type { ChoiceList } from '../../api/types';
import { Button, IconButton } from '../../components/Button';
import { Icon } from '../../components/Icon';
import { EmptyState, ErrorState, Header, Screen, Skeleton } from '../../components/Screen';
import { Text } from '../../components/Text';
import { Card, Ring, Switch } from '../../components/ui';
import { getToken } from '../../store/auth';
import { useSettings } from '../../store/settings';
import { fonts, useTheme } from '../../theme';

/** 18 · My choice list — overall chance, order, timeline, share as PDF. */
export default function MatchChoices() {
  const { t } = useTranslation();
  const { c } = useTheme();
  const router = useRouter();
  const q = useChoices();
  const save = useSaveChoices();
  const remind = useUpdateMatchProfile();
  const locale = useSettings((s) => s.appLocale);
  const [sharing, setSharing] = useState(false);

  const move = (g: ChoiceList['groups'][number], index: number, dir: -1 | 1) => {
    const ids = g.items.map((i) => i.university_id);
    const j = index + dir;
    if (j < 0 || j >= ids.length) return;
    [ids[index], ids[j]] = [ids[j], ids[index]];
    save.mutate({ specialtyId: g.specialty.id, universityIds: ids });
  };

  const share = async () => {
    setSharing(true);
    try {
      const target = `${FileSystem.cacheDirectory}medalbumin-choices.pdf`;
      const res = await FileSystem.downloadAsync(`${API_URL}/api/v1/match/choices/pdf`, target, {
        headers: { Authorization: `Bearer ${getToken() ?? ''}`, Accept: 'application/pdf', 'Accept-Language': locale ?? 'en' },
      });
      if (res.status !== 200) throw new Error(String(res.status));
      if (await Sharing.isAvailableAsync()) {
        await Sharing.shareAsync(res.uri, { mimeType: 'application/pdf', UTI: 'com.adobe.pdf', dialogTitle: t('match.share_title', 'My residency choice list') });
      }
    } catch {
      Alert.alert(t('match.share_failed', 'Couldn’t create the PDF'), t('common.error_body', 'Please try again.'));
    } finally {
      setSharing(false);
    }
  };

  return (
    <Screen onRefresh={() => q.refetch()}>
      <Header right={q.data?.groups.length ? <IconButton icon="share" label={t('match.share', 'Share as PDF')} onPress={share} active={sharing} /> : undefined} />
      <Text variant="title">{t('match.choices_title', 'My choice list')}</Text>

      {q.isError && !q.data ? (
        <ErrorState error={q.error} onRetry={() => q.refetch()} />
      ) : !q.data ? (
        <>
          <Skeleton height={110} radius={22} />
          <Skeleton height={300} />
        </>
      ) : q.data.groups.length === 0 ? (
        <EmptyState icon="pin" title={t('match.empty_list', 'Your list is empty')} body={t('match.empty_list_body', 'Pick specialties and add up to 3 universities for each.')} action={t('match.start', 'Start the predictor')} onAction={() => router.replace('/match')} />
      ) : (
        <>
          <Card tone="navy" style={{ padding: 18, flexDirection: 'row', alignItems: 'center', gap: 16 }}>
            <Ring value={q.data.overall_chance} color={c.accent} track="rgba(255,255,255,0.14)" label={`${q.data.overall_chance}%`} labelColor={c.onNavy} size={84} />
            <View style={{ flex: 1, gap: 4 }}>
              <Text style={{ fontFamily: fonts.display, fontSize: 18 }} color={c.onNavy}>
                {t('match.overall', 'Chance you match on this list')}
              </Text>
              <Text variant="caption" color={c.onNavySoft}>
                {t('match.overall_meta', 'Score {s} · {n} specialties · up to 3 universities each', { s: q.data.score, n: q.data.groups.length })}
              </Text>
            </View>
          </Card>

          {q.data.groups.map((g) => (
            <View key={g.specialty.id} style={{ gap: 8 }}>
              <View style={{ flexDirection: 'row', alignItems: 'baseline', justifyContent: 'space-between', gap: 8 }}>
                <Text variant="h2" style={{ flex: 1 }}>
                  {g.order} · {g.specialty.name}
                </Text>
                <Pressable onPress={() => router.push(`/match/chances?specialty=${g.specialty.id}`)} hitSlop={8} accessibilityRole="button">
                  <Text variant="smallStrong" color={c.teal}>
                    {t('match.edit', 'Edit')}
                  </Text>
                </Pressable>
              </View>
              {!g.has_safe_option ? (
                <Text variant="caption" color={c.warn}>
                  {t('match.no_safe', 'Tip: add one “likely” university as a safe option.')}
                </Text>
              ) : null}
              <Card>
                {g.items.map((i, idx) => (
                  <View key={i.university_id} style={{ flexDirection: 'row', alignItems: 'center', gap: 10, paddingLeft: 14, paddingRight: 6, minHeight: 58, borderTopWidth: idx ? 1 : 0, borderTopColor: c.divider }}>
                    <View style={{ width: 26, height: 26, borderRadius: 8, backgroundColor: c.chip, alignItems: 'center', justifyContent: 'center' }}>
                      <Text variant="mono" style={{ fontSize: 12 }}>
                        {i.rank}
                      </Text>
                    </View>
                    <Text variant="bodyMedium" style={{ flex: 1, fontSize: 14 }} numberOfLines={2}>
                      {i.name}
                    </Text>
                    <Text style={{ fontFamily: fonts.displayBold, fontSize: 16 }} color={i.tier === 'likely' ? c.teal : i.tier === 'possible' ? c.sand : c.danger}>
                      {i.chance}%
                    </Text>
                    <View>
                      <Pressable disabled={idx === 0} onPress={() => move(g, idx, -1)} accessibilityRole="button" accessibilityLabel={t('match.move_up', 'Move up')} style={{ width: 36, height: 24, alignItems: 'center', justifyContent: 'center', opacity: idx === 0 ? 0.25 : 1 }}>
                        <View style={{ transform: [{ rotate: '180deg' }] }}><Icon name="chevronDown" size={16} color={c.text} /></View>
                      </Pressable>
                      <Pressable disabled={idx === g.items.length - 1} onPress={() => move(g, idx, 1)} accessibilityRole="button" accessibilityLabel={t('match.move_down', 'Move down')} style={{ width: 36, height: 24, alignItems: 'center', justifyContent: 'center', opacity: idx === g.items.length - 1 ? 0.25 : 1 }}>
                        <Icon name="chevronDown" size={16} color={c.text} />
                      </Pressable>
                    </View>
                  </View>
                ))}
              </Card>
            </View>
          ))}

          <Card style={{ padding: 16, gap: 12 }}>
            <View style={{ flexDirection: 'row', alignItems: 'center', gap: 12 }}>
              <View style={{ flex: 1, gap: 2 }}>
                <Text variant="bodyStrong">{t('match.timeline', 'Matching timeline')}</Text>
                <Text variant="caption" muted>
                  {q.data.remind ? t('match.remind_on', 'We’ll remind you before each step') : t('match.remind_off', 'Get a reminder before each step')}
                </Text>
              </View>
              <Switch value={q.data.remind} onChange={(v) => remind.mutate({ remind: v })} label={t('match.remind', 'Remind me')} />
            </View>
            {q.data.timeline.map((s, i) => (
              <View key={s.title} style={{ flexDirection: 'row', alignItems: 'center', gap: 10 }}>
                <View style={{ width: 22, height: 22, borderRadius: 11, alignItems: 'center', justifyContent: 'center', backgroundColor: s.done ? c.teal : c.chip }}>
                  {s.done ? (
                    <Icon name="check" size={12} color="#FFFFFF" />
                  ) : (
                    <Text variant="caption" style={{ fontSize: 10 }} muted>
                      {i + 1}
                    </Text>
                  )}
                </View>
                <Text variant="small" style={{ flex: 1 }} color={s.done ? c.muted : c.text}>
                  {s.title}
                </Text>
              </View>
            ))}
          </Card>

          <Button title={t('match.raise', 'Raise my score · practise weak topics')} variant="soft" icon="target" onPress={() => router.push('/quiz/builder')} />
          <Text variant="caption" muted align="center">
            {q.data.disclaimer}
          </Text>
        </>
      )}
    </Screen>
  );
}
