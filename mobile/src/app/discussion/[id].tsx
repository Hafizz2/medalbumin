import { useQueryClient } from '@tanstack/react-query';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { useState } from 'react';
import { Alert, KeyboardAvoidingView, Platform, Pressable, TextInput, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { ApiError } from '../../api/client';
import { flagComment, postComment, qk, useComments, voteComment } from '../../api/hooks';
import type { Comment } from '../../api/types';
import { Button } from '../../components/Button';
import { Icon } from '../../components/Icon';
import { EmptyState, ErrorState, Header, Screen, Skeleton } from '../../components/Screen';
import { Text } from '../../components/Text';
import { Card, Chip, ChipRow } from '../../components/ui';
import { fmtAgo } from '../../lib/format';
import { useAuth } from '../../store/auth';
import { fonts, useTheme } from '../../theme';

type Sort = 'top' | 'new';

/** 25 · Question discussion */
export default function Discussion() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const questionId = Number(id);
  const { t } = useTranslation();
  const { c } = useTheme();
  const router = useRouter();
  const qc = useQueryClient();
  const isGuest = useAuth((s) => s.user?.is_guest ?? false);
  const [sort, setSort] = useState<Sort>('top');
  const [draft, setDraft] = useState('');
  const [kind, setKind] = useState<'doubt' | 'tip'>('doubt');
  const [replyTo, setReplyTo] = useState<Comment | null>(null);
  const [posting, setPosting] = useState(false);
  const q = useComments(questionId, sort);

  const first = q.data?.pages[0];
  const posts = q.data?.pages.flatMap((p) => p.data) ?? [];

  const refresh = () => qc.invalidateQueries({ queryKey: ['comments', questionId] });

  const send = async () => {
    const body = draft.trim();
    if (body.length < 3) return;
    setPosting(true);
    try {
      await postComment(questionId, body, replyTo ? undefined : kind, replyTo?.id);
      setDraft('');
      setReplyTo(null);
      if (sort !== 'new' && !replyTo) setSort('new');
      await refresh();
    } catch (e) {
      Alert.alert(t('disc.post_failed', 'Couldn’t post'), e instanceof ApiError ? e.message : t('common.error_body', 'Please try again.'));
    } finally {
      setPosting(false);
    }
  };

  const report = (comment: Comment) =>
    Alert.alert(t('disc.report_title', 'Report this post?'), t('disc.report_body', 'Our editors will review it.'), [
      { text: t('common.cancel', 'Cancel'), style: 'cancel' },
      { text: t('disc.report_spam', 'Spam or off-topic'), onPress: () => flag(comment, 'spam') },
      { text: t('disc.report_wrong', 'Medically wrong'), onPress: () => flag(comment, 'wrong') },
      { text: t('disc.report_abuse', 'Rude or abusive'), style: 'destructive', onPress: () => flag(comment, 'abuse') },
    ]);

  const flag = (comment: Comment, reason: string) =>
    flagComment(comment.id, reason)
      .then(() => Alert.alert(t('disc.reported', 'Thanks — reported')))
      .catch((e) => Alert.alert(t('common.error', 'Something went wrong'), e instanceof ApiError ? e.message : ''));

  const composer = isGuest ? (
    <Card style={{ padding: 14, gap: 10 }}>
      <Text variant="small">{t('disc.guest', 'Create a free account to ask doubts and share tips.')}</Text>
      <Button title={t('disc.sign_in', 'Sign in')} size="sm" onPress={() => router.push('/sign-in?mode=signup')} />
    </Card>
  ) : (
    <View style={{ gap: 8 }}>
      {replyTo ? (
        <View style={{ flexDirection: 'row', alignItems: 'center', gap: 8 }}>
          <Text variant="caption" muted style={{ flex: 1 }} numberOfLines={1}>
            {t('disc.replying', 'Replying to {name}', { name: replyTo.author.name })}
          </Text>
          <Pressable onPress={() => setReplyTo(null)} hitSlop={10} accessibilityRole="button" accessibilityLabel={t('common.cancel', 'Cancel')}>
            <Icon name="close" size={16} color={c.muted} />
          </Pressable>
        </View>
      ) : (
        <View style={{ flexDirection: 'row', gap: 6 }}>
          <Chip label={t('disc.doubt', 'Doubt')} selected={kind === 'doubt'} onPress={() => setKind('doubt')} />
          <Chip label={t('disc.tip', 'Tip')} selected={kind === 'tip'} onPress={() => setKind('tip')} />
        </View>
      )}
      <View style={{ flexDirection: 'row', gap: 8, alignItems: 'flex-end' }}>
        <TextInput
          value={draft}
          onChangeText={setDraft}
          multiline
          maxLength={2000}
          placeholder={t('disc.placeholder', 'Ask a doubt or share a tip')}
          placeholderTextColor={c.placeholder}
          accessibilityLabel={t('disc.placeholder', 'Ask a doubt or share a tip')}
          style={{ flex: 1, minHeight: 48, maxHeight: 130, borderRadius: 14, borderWidth: 1, borderColor: c.field, backgroundColor: c.card, paddingHorizontal: 14, paddingVertical: 13, fontFamily: fonts.body, fontSize: 15, color: c.text }}
        />
        <Button title={t('disc.post', 'Post')} size="sm" style={{ height: 48 }} loading={posting} disabled={draft.trim().length < 3} onPress={send} />
      </View>
    </View>
  );

  return (
    <KeyboardAvoidingView style={{ flex: 1, backgroundColor: c.page }} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
      <Screen
        onRefresh={() => q.refetch()}
        footer={composer}
      >
        <Header />
        {q.isError && !q.data ? (
          <ErrorState error={q.error} onRetry={() => q.refetch()} />
        ) : !first ? (
          <>
            <Skeleton height={60} />
            <Skeleton height={140} />
            <Skeleton height={240} />
          </>
        ) : (
          <>
            <View style={{ gap: 4 }}>
              <Text variant="h1">{t('disc.title', 'Discussion · {n}', { n: first.count })}</Text>
              <Text variant="small" muted numberOfLines={2}>
                {first.question.code} · {first.question.stem}
              </Text>
            </View>

            {first.verified ? (
              <Card tone="navy" style={{ padding: 16, gap: 8 }}>
                <View style={{ flexDirection: 'row', alignItems: 'center', gap: 8 }}>
                  <Icon name="shield" size={16} color={c.accent} />
                  <Text variant="overline" color={c.accent}>
                    {t('disc.verified', 'Verified answer · {name}', { name: first.verified.author.name })}
                  </Text>
                </View>
                <Text variant="small" color={c.onNavy} style={{ lineHeight: 20 }}>
                  {first.verified.body}
                </Text>
              </Card>
            ) : null}

            <ChipRow>
              <Chip label={t('disc.top', 'Top')} selected={sort === 'top'} onPress={() => setSort('top')} />
              <Chip label={t('disc.newest', 'Newest')} selected={sort === 'new'} onPress={() => setSort('new')} />
            </ChipRow>

            {posts.length === 0 ? (
              <EmptyState icon="chat" title={t('disc.empty', 'No posts yet')} body={t('disc.empty_body', 'Be the first to ask a doubt or share a memory trick.')} />
            ) : (
              posts.map((p) => <Post key={p.id} p={p} onReply={isGuest ? undefined : () => setReplyTo(p)} onReport={report} onVoted={refresh} />)
            )}

            {q.hasNextPage ? <Button title={t('disc.more', 'Load more')} variant="soft" size="md" loading={q.isFetchingNextPage} onPress={() => q.fetchNextPage()} /> : null}
          </>
        )}
      </Screen>
    </KeyboardAvoidingView>
  );
}

function Post({ p, onReply, onReport, onVoted, nested }: { p: Comment; onReply?: () => void; onReport: (c: Comment) => void; onVoted: () => void; nested?: boolean }) {
  const { t } = useTranslation();
  const { c } = useTheme();
  const [voted, setVoted] = useState(p.voted);
  const [votes, setVotes] = useState(p.votes);

  const vote = async () => {
    // Optimistic toggle; server is the source of truth.
    setVoted(!voted);
    setVotes(votes + (voted ? -1 : 1));
    try {
      const r = await voteComment(p.id);
      setVoted(r.voted);
      setVotes(r.votes);
    } catch (e) {
      setVoted(voted);
      setVotes(votes);
      if (e instanceof ApiError && e.message) Alert.alert(e.message);
    }
    onVoted();
  };

  const faculty = p.author.role === 'faculty';
  return (
    <View style={{ gap: 8, marginLeft: nested ? 44 : 0 }}>
      <View style={{ flexDirection: 'row', gap: 12 }}>
        <View style={{ width: 34, height: 34, borderRadius: 17, backgroundColor: faculty ? '#062A5E' : c.chip, alignItems: 'center', justifyContent: 'center' }}>
          <Text style={{ fontFamily: fonts.bodySemibold, fontSize: 12 }} color={faculty ? c.accent : c.textSoft}>
            {p.author.initials}
          </Text>
        </View>
        <View style={{ flex: 1, gap: 4 }}>
          <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6, flexWrap: 'wrap' }}>
            <Text variant="smallStrong">{p.author.name}</Text>
            {faculty ? <Icon name="shield" size={12} color={c.teal} /> : null}
            {p.kind === 'tip' ? (
              <Text variant="caption" color={c.sand}>
                {t('disc.tip', 'Tip')}
              </Text>
            ) : null}
            <Text variant="caption" muted>
              {fmtAgo(p.created_at)}
            </Text>
          </View>
          <Text variant="small" style={{ lineHeight: 20 }}>
            {p.body}
          </Text>
          <View style={{ flexDirection: 'row', alignItems: 'center', gap: 4, marginLeft: -8 }}>
            <Pressable onPress={vote} accessibilityRole="button" accessibilityState={{ selected: voted }} accessibilityLabel={t('disc.upvote', 'Upvote, {n} votes', { n: votes })} style={{ minHeight: 36, paddingHorizontal: 8, justifyContent: 'center' }}>
              <Text variant="smallStrong" color={voted ? c.teal : c.textSoft}>
                ▲ {votes}
              </Text>
            </Pressable>
            {onReply ? (
              <Pressable onPress={onReply} accessibilityRole="button" style={{ minHeight: 36, paddingHorizontal: 8, justifyContent: 'center' }}>
                <Text variant="small" color={c.textSoft}>
                  {t('disc.reply', 'Reply')}
                </Text>
              </Pressable>
            ) : null}
            {!p.is_mine ? (
              <Pressable onPress={() => onReport(p)} accessibilityRole="button" style={{ minHeight: 36, paddingHorizontal: 8, justifyContent: 'center' }}>
                <Text variant="small" color={c.textSoft}>
                  {t('disc.report', 'Report')}
                </Text>
              </Pressable>
            ) : null}
          </View>
        </View>
      </View>
      {p.replies?.map((r) => <Post key={r.id} p={r} nested onReport={onReport} onVoted={onVoted} />)}
    </View>
  );
}
