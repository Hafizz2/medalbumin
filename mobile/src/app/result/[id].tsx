import { useLocalSearchParams, useRouter } from 'expo-router';
import { ScrollView, Share, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useTranslation } from 'react-i18next';
import { useResult } from '../../api/hooks';
import { Button, IconButton } from '../../components/Button';
import { ErrorState, Loading } from '../../components/Screen';
import { Text } from '../../components/Text';
import { Card } from '../../components/ui';
import { useQuizLauncher } from '../../hooks/useQuizLauncher';
import { fmtDuration } from '../../lib/format';
import { fonts, useTheme } from '../../theme';

/** 06 · Mock exam result */
export default function Result() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const sessionId = Number(id);
  const { t } = useTranslation();
  const { c } = useTheme();
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const q = useResult(sessionId);
  const { launch, starting } = useQuizLauncher();

  if (q.isError && !q.data) {
    return (
      <View style={{ flex: 1, backgroundColor: c.page, paddingTop: insets.top + 40 }}>
        <ErrorState error={q.error} onRetry={() => q.refetch()} />
      </View>
    );
  }
  if (!q.data) {
    return (
      <View style={{ flex: 1, backgroundColor: c.page, paddingTop: insets.top + 80 }}>
        <Loading />
      </View>
    );
  }

  const { session, result: r } = q.data;
  const share = () =>
    Share.share({ message: t('result.share', 'I scored {score}% on “{title}” in MedAlbumin.', { score: r.score_pct, title: session.title }) }).catch(() => {});

  const stats = [
    r.top_pct !== null ? [t('result.top', 'Top {pct}%', { pct: r.top_pct }), t('result.of_takers', 'of {count} takers', { count: r.takers ?? 0 })] : [`${r.answered}/${r.total}`, t('result.answered', 'answered')],
    [fmtDuration(r.time_spent_seconds), r.time_limit_seconds ? t('result.of_time', 'of {time}', { time: fmtDuration(r.time_limit_seconds) }) : t('result.time_spent', 'time spent')],
    [`${r.seconds_per_question}s`, t('result.per_question', 'per question')],
  ];

  return (
    <View style={{ flex: 1, backgroundColor: c.page }}>
      <ScrollView contentContainerStyle={{ paddingBottom: insets.bottom + 110 }} showsVerticalScrollIndicator={false}>
        <View style={{ backgroundColor: '#062A5E', paddingTop: insets.top + 10, paddingHorizontal: 20, paddingBottom: 26, borderBottomLeftRadius: 32, borderBottomRightRadius: 32, gap: 18 }}>
          <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 10 }}>
            <IconButton icon="close" tone="navy" label={t('common.close', 'Close')} onPress={() => router.dismissTo('/(tabs)')} />
            <Text variant="small" color="#A9BBD8" numberOfLines={1} style={{ flex: 1, textAlign: 'center' }}>
              {session.title}
            </Text>
            <IconButton icon="share" tone="navy" label={t('result.share_label', 'Share result')} onPress={share} />
          </View>

          <View style={{ gap: 4 }}>
            <Text variant="label" color={r.passed ? c.accent : '#FBBF77'}>
              {r.passed ? t('result.passed', 'Passed') : t('result.not_yet', 'Not yet')}
            </Text>
            <View style={{ flexDirection: 'row', alignItems: 'baseline', gap: 10 }}>
              <Text style={{ fontFamily: fonts.displayBold, fontSize: 76, lineHeight: 80, letterSpacing: -3.5 }} color="#F3F6FA" accessibilityLabel={`${r.score_pct}%`}>
                {r.score_pct}
                <Text style={{ fontFamily: fonts.displayBold, fontSize: 40 }} color="#F3F6FA">
                  %
                </Text>
              </Text>
              <Text variant="mono" style={{ fontSize: 15 }} color="#A9BBD8">
                {r.correct} / {r.total}
              </Text>
            </View>
          </View>

          <View style={{ height: 10, borderRadius: 99, backgroundColor: '#1B4A8A' }}>
            <View style={{ width: `${r.score_pct}%`, height: '100%', borderRadius: 99, backgroundColor: c.accent }} />
            <View style={{ position: 'absolute', left: `${r.pass_mark}%`, top: -5, width: 2, height: 20, backgroundColor: '#F3F6FA' }} />
          </View>
          <View style={{ flexDirection: 'row', justifyContent: 'space-between' }}>
            <Text variant="caption" color="#A9BBD8">
              {t('result.pass_mark', 'Pass mark {pct}%', { pct: r.pass_mark })}
            </Text>
            {r.delta !== null ? (
              <Text variant="caption" color="#A9BBD8">
                {r.delta >= 0 ? '+' : ''}
                {t('result.since_last', '{delta} since last time', { delta: r.delta })}
              </Text>
            ) : null}
          </View>

          <View style={{ flexDirection: 'row', gap: 8 }}>
            {stats.map(([v, l]) => (
              <View key={l} style={{ flex: 1, backgroundColor: '#0D3672', borderRadius: 14, paddingVertical: 10, paddingHorizontal: 12, gap: 2 }}>
                <Text variant="monoLarge" color="#F3F6FA" numberOfLines={1} adjustsFontSizeToFit>
                  {v}
                </Text>
                <Text style={{ fontSize: 11 }} color="#94A6C4" numberOfLines={1}>
                  {l}
                </Text>
              </View>
            ))}
          </View>
        </View>

        <View style={{ padding: 20, gap: 12 }}>
          <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'baseline' }}>
            <Text variant="h2" style={{ fontSize: 20 }}>
              {t('result.by_system', 'By system')}
            </Text>
            <Text variant="caption" muted>
              {t('result.weakest_first', 'Weakest first')}
            </Text>
          </View>
          <Card style={{ paddingVertical: 6, paddingHorizontal: 16, borderRadius: 20 }}>
            {r.by_system.map((s) => {
              const weak = s.pct < 60;
              return (
                <View key={`${s.department_id}-${s.subcategory_id}-${s.name}`} style={{ flexDirection: 'row', alignItems: 'center', gap: 12, minHeight: 40 }}>
                  <Text style={{ width: 110, fontFamily: fonts.bodyMedium, fontSize: 14 }} numberOfLines={1}>
                    {s.name}
                  </Text>
                  <View style={{ flex: 1, height: 8, borderRadius: 99, backgroundColor: c.divider, overflow: 'hidden' }}>
                    <View style={{ width: `${s.pct}%`, height: '100%', borderRadius: 99, backgroundColor: weak ? c.danger2 : c.teal }} />
                  </View>
                  <Text style={{ width: 40, textAlign: 'right', fontFamily: fonts.mono, fontSize: 13 }} color={weak ? c.danger : c.text}>
                    {s.pct}%
                  </Text>
                </View>
              );
            })}
          </Card>
        </View>
      </ScrollView>

      <View style={{ position: 'absolute', left: 0, right: 0, bottom: 0, flexDirection: 'row', gap: 10, paddingHorizontal: 20, paddingTop: 10, paddingBottom: insets.bottom + 12, backgroundColor: c.page }}>
        <Button title={t('result.review', 'Review answers')} variant="light" style={{ flex: 1 }} onPress={() => router.push(`/quiz/${sessionId}`)} />
        {r.wrong > 0 ? (
          <Button title={t('result.retry_wrong', 'Retry {count} wrong', { count: r.wrong })} style={{ flex: 1 }} loading={starting} onPress={() => launch({ source: 'retry', session_id: sessionId })} />
        ) : (
          <Button title={t('result.home', 'Back home')} style={{ flex: 1 }} onPress={() => router.dismissTo('/(tabs)')} />
        )}
      </View>
    </View>
  );
}
