import * as FileSystem from 'expo-file-system/legacy';
import { useLocalSearchParams } from 'expo-router';
import { useEventListener } from 'expo';
import { useVideoPlayer, VideoView } from 'expo-video';
import { useEffect, useMemo, useRef, useState } from 'react';
import { Alert, Pressable, TextInput, View } from 'react-native';
import { WebView } from 'react-native-webview';
import { useTranslation } from 'react-i18next';
import { saveVideoProgress, useSaveMyNote, useVideo } from '../../api/hooks';
import type { VideoDetail } from '../../api/types';
import { Button } from '../../components/Button';
import { Icon } from '../../components/Icon';
import { EmptyState, ErrorState, Header, Screen, Skeleton } from '../../components/Screen';
import { Text } from '../../components/Text';
import { Card } from '../../components/ui';
import { useQuizLauncher } from '../../hooks/useQuizLauncher';
import { fmtDuration, fmtNumber } from '../../lib/format';
import { fonts, useTheme } from '../../theme';

const SPEEDS = [1, 1.25, 1.5, 2];
const offlinePath = (id: number) => `${FileSystem.documentDirectory}video-${id}.mp4`;

/** 22 · Video lesson */
export default function VideoLesson() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const q = useVideo(Number(id));
  const { c } = useTheme();
  const { t } = useTranslation();

  return (
    <Screen onRefresh={() => q.refetch()}>
      <Header />
      {q.isError && !q.data ? (
        <ErrorState error={q.error} onRetry={() => q.refetch()} />
      ) : !q.data ? (
        <>
          <Skeleton height={210} radius={20} />
          <Skeleton height={200} />
        </>
      ) : q.data.locked ? (
        <EmptyState icon="lock" title={t('common.premium_title', 'Premium content')} body={t('video.locked', 'This lesson is part of the premium plan. Your plan status is under Profile.')} />
      ) : (
        <Lesson v={q.data} accent={c.accent} />
      )}
    </Screen>
  );
}

function Lesson({ v }: { v: VideoDetail; accent: string }) {
  const { t } = useTranslation();
  const { c } = useTheme();
  const save = useSaveMyNote();
  const { launch, starting } = useQuizLauncher();
  const youtube = v.streams.find((s) => s.youtube_id);
  const [quality, setQuality] = useState(v.streams[0]?.quality ?? 'source');
  const [local, setLocal] = useState<string | null>(null);
  const [downloading, setDownloading] = useState<number | null>(null);
  const [speed, setSpeed] = useState(1);
  const [position, setPosition] = useState(v.position_seconds);
  const [draft, setDraft] = useState('');
  const watched = useRef(0);
  const lastSent = useRef(Date.now());

  useEffect(() => {
    if (youtube) return;
    FileSystem.getInfoAsync(offlinePath(v.id))
      .then((info) => info.exists && setLocal(info.uri))
      .catch(() => {});
  }, [v.id, youtube]);

  const uri = local ?? v.streams.find((s) => s.quality === quality)?.url ?? v.streams[0]?.url;
  const player = useVideoPlayer(youtube || !uri ? null : { uri }, (p) => {
    p.timeUpdateEventInterval = 1;
    if (v.position_seconds > 5 && !v.completed) p.currentTime = v.position_seconds;
  });

  useEventListener(player, 'timeUpdate', ({ currentTime }) => {
    setPosition(currentTime);
    if (player.playing) watched.current += 1;
    // Save progress every 15 s of watching.
    if (Date.now() - lastSent.current > 15000) {
      lastSent.current = Date.now();
      void saveVideoProgress(v.id, currentTime, watched.current).catch(() => {});
      watched.current = 0;
    }
  });

  // Final save when leaving the screen.
  useEffect(() => () => void saveVideoProgress(v.id, player?.currentTime ?? position, watched.current).catch(() => {}), []); // eslint-disable-line react-hooks/exhaustive-deps

  const seek = (sec: number) => {
    if (!player) return;
    player.currentTime = sec;
    player.play();
  };

  const cycleSpeed = () => {
    const next = SPEEDS[(SPEEDS.indexOf(speed) + 1) % SPEEDS.length];
    setSpeed(next);
    if (player) player.playbackRate = next;
  };

  const download = async () => {
    if (local) {
      await FileSystem.deleteAsync(local, { idempotent: true });
      setLocal(null);
      return;
    }
    const small = v.streams.find((s) => s.quality === '360') ?? v.streams[v.streams.length - 1];
    if (!small) return;
    try {
      setDownloading(0);
      const task = FileSystem.createDownloadResumable(small.url, offlinePath(v.id), {}, (p) => setDownloading(p.totalBytesExpectedToWrite ? p.totalBytesWritten / p.totalBytesExpectedToWrite : 0));
      const res = await task.downloadAsync();
      if (res?.uri) setLocal(res.uri);
    } catch {
      Alert.alert(t('video.download_failed', 'Download failed'), t('video.download_failed_body', 'Check your connection and try again.'));
    } finally {
      setDownloading(null);
    }
  };

  const currentChapter = useMemo(() => {
    let idx = 0;
    v.chapters.forEach((ch, i) => {
      if (position >= ch.start_seconds) idx = i;
    });
    return idx;
  }, [position, v.chapters]);

  const addNote = () => {
    const body = draft.trim();
    if (!body) return;
    save.mutate({ video_id: v.id, video_seconds: Math.floor(position), body, title: v.title }, { onSuccess: () => setDraft('') });
  };

  return (
    <>
      <View style={{ borderRadius: 20, overflow: 'hidden', backgroundColor: '#062A5E', aspectRatio: 16 / 9 }}>
        {youtube ? (
          <WebView
            source={{ uri: `https://www.youtube-nocookie.com/embed/${youtube.youtube_id}?playsinline=1&rel=0&modestbranding=1` }}
            allowsFullscreenVideo
            allowsInlineMediaPlayback
            mediaPlaybackRequiresUserAction={false}
            style={{ backgroundColor: '#000' }}
          />
        ) : player ? (
          <VideoView player={player} style={{ width: '100%', height: '100%' }} nativeControls fullscreenOptions={{ enable: true }} allowsPictureInPicture contentFit="contain" />
        ) : null}
      </View>

      {v.chapters.length ? (
        <View style={{ flexDirection: 'row', gap: 3 }}>
          {v.chapters.map((ch, i) => (
            <View key={ch.start_seconds} style={{ flex: Math.max(1, (v.chapters[i + 1]?.start_seconds ?? v.duration_seconds) - ch.start_seconds), height: 4, borderRadius: 99, backgroundColor: i < currentChapter ? c.accent : i === currentChapter ? c.text : c.field }} />
          ))}
        </View>
      ) : null}

      <View style={{ gap: 6 }}>
        <Text variant="caption" muted>
          {v.breadcrumb}
        </Text>
        <Text variant="h1" style={{ fontSize: 24, lineHeight: 29 }}>
          {v.title}
        </Text>
        <Text variant="small" muted>
          {[v.lecturer, `${Math.max(1, Math.round(v.duration_seconds / 60))} min`, t('video.views', '{n} views', { n: fmtNumber(v.views) })].filter(Boolean).join(' · ')}
        </Text>
      </View>

      {!youtube ? (
        <View style={{ flexDirection: 'row', gap: 8, flexWrap: 'wrap' }}>
          <Pill label={`${speed}×`} onPress={cycleSpeed} a11y={t('video.speed', 'Playback speed')} />
          {v.streams.length > 1 && !local
            ? v.streams.map((s) => <Pill key={s.quality} label={s.quality === 'source' ? 'HD' : `${s.quality}p`} active={s.quality === quality} onPress={() => setQuality(s.quality)} a11y={t('video.quality', 'Quality')} />)
            : null}
          <Pill
            icon="download"
            label={downloading !== null ? `${Math.round(downloading * 100)}%` : local ? t('video.saved_offline', 'Saved offline') : t('video.save_offline', 'Save offline')}
            active={!!local}
            onPress={download}
            a11y={t('video.save_offline', 'Save offline')}
          />
        </View>
      ) : null}

      {v.description ? <Text variant="small">{v.description}</Text> : null}

      {v.chapters.length ? (
        <Card>
          {v.chapters.map((ch, i) => (
            <Pressable key={ch.start_seconds} onPress={() => seek(ch.start_seconds)} accessibilityRole="button" style={{ minHeight: 46, paddingHorizontal: 14, flexDirection: 'row', alignItems: 'center', gap: 10, borderTopWidth: i ? 1 : 0, borderTopColor: c.divider, backgroundColor: i === currentChapter ? c.tealWash : 'transparent' }}>
              <Text variant="mono" style={{ fontSize: 12, width: 48 }} muted>
                {fmtDuration(ch.start_seconds, true)}
              </Text>
              <Text style={{ flex: 1, fontFamily: i === currentChapter ? fonts.bodySemibold : fonts.bodyMedium, fontSize: 14 }}>{ch.title}</Text>
              {i === currentChapter ? (
                <Text variant="caption" color={c.teal}>
                  {t('video.playing', 'Playing')}
                </Text>
              ) : null}
            </Pressable>
          ))}
        </Card>
      ) : null}

      <Card tone="teal" style={{ padding: 14, gap: 10 }}>
        <Text variant="smallStrong">{t('video.my_notes', 'My notes on this lesson')}</Text>
        {v.my_notes.map((n) => (
          <Pressable key={n.id} onPress={() => n.video_seconds !== null && seek(n.video_seconds)} style={{ flexDirection: 'row', gap: 10 }}>
            <Text variant="mono" style={{ fontSize: 12 }} color={c.teal}>
              {fmtDuration(n.video_seconds ?? 0, true)}
            </Text>
            <Text variant="small" style={{ flex: 1 }}>
              {n.body}
            </Text>
          </Pressable>
        ))}
        {!youtube ? (
          <View style={{ flexDirection: 'row', gap: 8, alignItems: 'flex-end' }}>
            <TextInput
              value={draft}
              onChangeText={setDraft}
              placeholder={t('video.note_at', 'Note at {time}…', { time: fmtDuration(position, true) })}
              placeholderTextColor={c.placeholder}
              multiline
              style={{ flex: 1, minHeight: 46, maxHeight: 120, borderRadius: 14, backgroundColor: c.card, borderWidth: 1, borderColor: c.line, paddingHorizontal: 12, paddingVertical: 12, color: c.text, fontFamily: fonts.body, fontSize: 14 }}
            />
            <Button title={t('note.save', 'Save')} size="sm" style={{ height: 46 }} loading={save.isPending} disabled={!draft.trim()} onPress={addNote} />
          </View>
        ) : null}
      </Card>

      {v.linked_question_ids.length ? (
        <Button
          title={t('video.practise_linked', 'Practise linked questions')}
          loading={starting}
          onPress={() => launch({ source: 'questions', question_ids: v.linked_question_ids, title: v.title })}
          trailing={
            <Text variant="mono" style={{ fontSize: 13 }} color="#A9BBD8">
              {v.linked_question_ids.length} Q
            </Text>
          }
        />
      ) : null}
    </>
  );
}

function Pill({ label, onPress, active, icon, a11y }: { label: string; onPress: () => void; active?: boolean; icon?: 'download'; a11y: string }) {
  const { c } = useTheme();
  return (
    <Pressable onPress={onPress} accessibilityRole="button" accessibilityLabel={a11y} style={{ height: 38, paddingHorizontal: 12, borderRadius: 12, flexDirection: 'row', alignItems: 'center', gap: 6, borderWidth: 1, borderColor: active ? c.teal : c.field, backgroundColor: active ? c.tealSoft : c.card }}>
      {icon ? <Icon name={icon} size={15} color={active ? c.teal : c.text} /> : null}
      <Text style={{ fontFamily: fonts.bodySemibold, fontSize: 13 }} color={active ? c.teal : c.text}>
        {label}
      </Text>
    </Pressable>
  );
}
