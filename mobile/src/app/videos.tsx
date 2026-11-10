import { Image } from 'expo-image';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { useVideos } from '../api/hooks';
import { Icon } from '../components/Icon';
import { EmptyState, ErrorState, Header, Screen, Skeleton } from '../components/Screen';
import { Text } from '../components/Text';
import { Badge, Card, ProgressBar } from '../components/ui';
import { fmtNumber } from '../lib/format';
import { useTheme } from '../theme';

/** Video lessons, optionally for one subcategory. */
export default function Videos() {
  const { subcategory, title } = useLocalSearchParams<{ subcategory?: string; title?: string }>();
  const { t } = useTranslation();
  const { c } = useTheme();
  const router = useRouter();
  const q = useVideos(subcategory ? Number(subcategory) : undefined);

  return (
    <Screen onRefresh={() => q.refetch()}>
      <Header title={title ?? t('video.lessons', 'Video lessons')} />
      <Text variant="h1">{t('video.lessons', 'Video lessons')}</Text>
      {q.isError && !q.data ? (
        <ErrorState error={q.error} onRetry={() => q.refetch()} />
      ) : !q.data ? (
        <Skeleton height={300} />
      ) : q.data.data.length === 0 ? (
        <EmptyState icon="video" title={t('video.none', 'No lessons yet')} />
      ) : (
        q.data.data.map((v) => (
          <Card key={v.id} onPress={() => router.push(`/video/${v.id}`)} style={{ padding: 10, flexDirection: 'row', gap: 12, alignItems: 'center', borderRadius: 18 }}>
            <View style={{ width: 112, height: 66, borderRadius: 12, overflow: 'hidden', backgroundColor: '#062A5E', alignItems: 'center', justifyContent: 'center' }}>
              {v.thumbnail_url ? <Image source={{ uri: v.thumbnail_url }} style={{ position: 'absolute', width: '100%', height: '100%' }} contentFit="cover" /> : null}
              <View style={{ width: 30, height: 30, borderRadius: 15, backgroundColor: 'rgba(6,42,94,0.7)', alignItems: 'center', justifyContent: 'center' }}>
                <Icon name={v.locked ? 'lock' : 'play'} size={14} color="#FFFFFF" fill={v.locked ? 'none' : '#FFFFFF'} />
              </View>
            </View>
            <View style={{ flex: 1, gap: 4 }}>
              <Text variant="bodyStrong" numberOfLines={2} style={{ fontSize: 14 }}>
                {v.title}
              </Text>
              <Text variant="caption" muted numberOfLines={1}>
                {Math.max(1, Math.round(v.duration_seconds / 60))} min · {t('video.views', '{n} views', { n: fmtNumber(v.views) })}
              </Text>
              {v.completed ? (
                <Badge label={t('video.watched', 'Watched')} tone="ok" />
              ) : v.position_seconds > 0 ? (
                <ProgressBar value={(v.position_seconds / Math.max(1, v.duration_seconds)) * 100} height={4} />
              ) : v.locked ? (
                <Badge label={t('papers.premium', 'Premium')} tone="navy" />
              ) : null}
            </View>
          </Card>
        ))
      )}
    </Screen>
  );
}
