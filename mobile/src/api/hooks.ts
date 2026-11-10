import { useInfiniteQuery, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api } from './client';
import type * as T from './types';

/** Query keys in one place so mutations can refresh the right screens. */
export const qk = {
  config: ['config'] as const,
  me: ['me'] as const,
  home: ['home'] as const,
  departments: ['departments'] as const,
  department: (id: number) => ['department', id] as const,
  papers: (cat: string, q: string) => ['papers', cat, q] as const,
  builder: ['quiz-options'] as const,
  session: (id: number) => ['session', id] as const,
  result: (id: number) => ['result', id] as const,
  overview: ['progress', 'overview'] as const,
  subjects: ['progress', 'subjects'] as const,
  mocks: ['progress', 'mocks'] as const,
  decks: ['decks'] as const,
  study: (deck?: number) => ['flash-study', deck ?? 'all'] as const,
  notes: (sub?: number) => ['notes', sub ?? 'all'] as const,
  note: (id: number) => ['note', id] as const,
  myNotes: ['my-notes'] as const,
  videos: (sub?: number) => ['videos', sub ?? 'all'] as const,
  video: (id: number) => ['video', id] as const,
  search: (q: string, type: string) => ['search', q, type] as const,
  images: ['image-bank'] as const,
  labs: ['labs'] as const,
  drugs: ['drugs'] as const,
  comments: (qid: number, sort: string) => ['comments', qid, sort] as const,
  exams: ['exams'] as const,
  leaderboard: (id: number, scope: string) => ['leaderboard', id, scope] as const,
  matchProfile: ['match', 'profile'] as const,
  specialties: ['match', 'specialties'] as const,
  chances: (id: number) => ['match', 'chances', id] as const,
  choices: ['match', 'choices'] as const,
  plan: (date?: string) => ['study-plan', date ?? 'today'] as const,
  notifications: ['notifications'] as const,
  notificationSettings: ['notification-settings'] as const,
  subscription: ['subscription'] as const,
  devices: ['devices'] as const,
  referral: ['referral'] as const,
  universities: ['universities'] as const,
};

// ---------- App & account ----------

export const useConfig = () => useQuery({ queryKey: qk.config, queryFn: () => api<T.AppConfig>('/config'), staleTime: 10 * 60_000 });
export const useMe = (enabled = true) => useQuery({ queryKey: qk.me, queryFn: () => api<T.User>('/me'), enabled });
export const useUniversities = () => useQuery({ queryKey: qk.universities, queryFn: () => api<{ data: { id: number; name: string; short_name: string | null }[] }>('/universities'), staleTime: 60 * 60_000 });
export const useSubscription = () => useQuery({ queryKey: qk.subscription, queryFn: () => api<{ active: boolean; plan: string | null; until: string | null; pending_payment: boolean }>('/subscription') });
export const useDevices = () => useQuery({ queryKey: qk.devices, queryFn: () => api<{ limit: number; devices: { id: number; name: string | null; platform: string; last_seen_at: string | null; current: boolean }[] }>('/me/devices') });
export const useReferral = () => useQuery({ queryKey: qk.referral, queryFn: () => api<{ code: string; share_text: string; reward_days: number; max_rewards: number; joined: number; rewarded: number }>('/me/referral') });

export function useUpdateMe() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (body: Partial<T.User> & { university_id?: number | null }) => api<T.User>('/me', { method: 'PATCH', body }),
    onSuccess: (user) => {
      qc.setQueryData(qk.me, user);
      qc.invalidateQueries({ queryKey: qk.home });
    },
  });
}

// ---------- Home & study ----------

export const useHome = () => useQuery({ queryKey: qk.home, queryFn: () => api<T.Home>('/home') });
export const useDepartments = () => useQuery({ queryKey: qk.departments, queryFn: () => api<{ total_questions: number; data: T.DepartmentSummary[] }>('/departments') });
export const useDepartment = (id: number) => useQuery({ queryKey: qk.department(id), queryFn: () => api<T.DepartmentDetail>(`/departments/${id}`) });
export const usePapers = (category: string, q = '') =>
  useQuery({
    queryKey: qk.papers(category, q),
    queryFn: () => api<{ category: string; data: (T.Paper | T.PaperGroup)[] }>(`/papers?category=${category}${q ? `&q=${encodeURIComponent(q)}` : ''}`),
  });
export const useBuilderOptions = () => useQuery({ queryKey: qk.builder, queryFn: () => api<T.BuilderOptions>('/quiz/options') });

// ---------- Quiz ----------

export type StartQuiz = {
  source: 'builder' | 'subcategory' | 'mistakes' | 'bookmarks' | 'paper' | 'exam' | 'retry' | 'questions';
  mode?: 'practice' | 'timed';
  size?: number;
  department_ids?: number[];
  subcategory_ids?: number[];
  topic_id?: number;
  pools?: string[];
  paper_id?: number;
  exam_id?: number;
  session_id?: number;
  question_ids?: number[];
  title?: string;
};

export function useStartQuiz() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (body: StartQuiz) => api<T.SessionPayload>('/quiz-sessions', { body }),
    onSuccess: (payload) => qc.setQueryData(qk.session(payload.session.id), payload),
  });
}

export const useSession = (id: number) => useQuery({ queryKey: qk.session(id), queryFn: () => api<T.SessionPayload>(`/quiz-sessions/${id}`), staleTime: Infinity });

export const answerQuestion = (sessionId: number, body: { question_id: number; option: string | null; time_ms: number; flagged?: boolean }) =>
  api<T.AnswerResponse>(`/quiz-sessions/${sessionId}/answers`, { body });

export function useFinishQuiz() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => api<{ session: T.QuizSession; result: T.QuizResult }>(`/quiz-sessions/${id}/finish`, { method: 'POST' }),
    onSuccess: (data, id) => {
      qc.setQueryData(qk.result(id), data);
      qc.invalidateQueries({ queryKey: qk.session(id) });
      for (const key of [qk.home, qk.departments, qk.overview, qk.subjects, qk.mocks, qk.builder]) qc.invalidateQueries({ queryKey: key });
    },
  });
}

export const useResult = (id: number) => useQuery({ queryKey: qk.result(id), queryFn: () => api<{ session: T.QuizSession; result: T.QuizResult }>(`/quiz-sessions/${id}/result`) });

export const toggleBookmark = (questionId: number) => api<{ bookmarked: boolean }>(`/questions/${questionId}/bookmark`, { method: 'POST' });
export const reportQuestion = (questionId: number, reason: string, note?: string) => api(`/questions/${questionId}/report`, { body: { reason, note } });

// ---------- Progress ----------

export const useOverview = () => useQuery({ queryKey: qk.overview, queryFn: () => api<T.ProgressOverview>('/progress/overview') });
export const useSubjects = () => useQuery({ queryKey: qk.subjects, queryFn: () => api<{ data: T.SubjectRow[] }>('/progress/subjects') });
export const useMocks = () => useQuery({ queryKey: qk.mocks, queryFn: () => api<{ data: T.MockRow[] }>('/progress/mocks') });

// ---------- Flashcards ----------

export const useDecks = () => useQuery({ queryKey: qk.decks, queryFn: () => api<{ total_cards: number; due_today: number; reviewed_today: number; data: T.Deck[] }>('/flashcards/decks') });
export const useFlashStudy = (deckId?: number) =>
  useQuery({ queryKey: qk.study(deckId), queryFn: () => api<{ deck: { id: number; title: string; path: string } | null; due_count: number; data: T.Flashcard[] }>(`/flashcards/study?new=10${deckId ? `&deck_id=${deckId}` : ''}`), staleTime: Infinity });
export const reviewCard = (id: number, rating: number) => api<{ next_in: string }>(`/flashcards/${id}/review`, { body: { rating } });
export function useCardsFromMistakes() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: () => api<{ deck_id: number; added: number; message: string }>('/flashcards/from-mistakes', { method: 'POST' }),
    onSuccess: () => qc.invalidateQueries({ queryKey: qk.decks }),
  });
}

// ---------- Notes ----------

export const useOfficialNotes = (subcategoryId?: number) =>
  useQuery({ queryKey: qk.notes(subcategoryId), queryFn: () => api<T.Paginated<T.OfficialNoteRow>>(`/notes${subcategoryId ? `?subcategory_id=${subcategoryId}` : ''}`) });
export const useNote = (id: number) => useQuery({ queryKey: qk.note(id), queryFn: () => api<T.NoteDetail>(`/notes/${id}`), staleTime: 30 * 60_000 });
export const useMyNotes = () => useQuery({ queryKey: qk.myNotes, queryFn: () => api<T.Paginated<T.MyNote>>('/my-notes') });

export function useSaveMyNote() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ id, ...body }: { id?: number; title?: string | null; body: string; note_id?: number; question_id?: number; video_id?: number; video_seconds?: number }) =>
      api<T.MyNote>(id ? `/my-notes/${id}` : '/my-notes', { method: id ? 'PATCH' : 'POST', body }),
    onSuccess: (_note, vars) => {
      qc.invalidateQueries({ queryKey: qk.myNotes });
      if (vars.note_id) qc.invalidateQueries({ queryKey: qk.note(vars.note_id) });
      if (vars.video_id) qc.invalidateQueries({ queryKey: qk.video(vars.video_id) });
    },
  });
}

export function useDeleteMyNote() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => api(`/my-notes/${id}`, { method: 'DELETE' }),
    onSuccess: () => qc.invalidateQueries({ queryKey: qk.myNotes }),
  });
}

// ---------- Videos, search, tools ----------

export const useVideos = (subcategoryId?: number) =>
  useQuery({ queryKey: qk.videos(subcategoryId), queryFn: () => api<T.Paginated<T.VideoCard>>(`/videos${subcategoryId ? `?subcategory_id=${subcategoryId}` : ''}`) });
export const useVideo = (id: number) => useQuery({ queryKey: qk.video(id), queryFn: () => api<T.VideoDetail>(`/videos/${id}`) });
export const saveVideoProgress = (id: number, position: number, watched: number) =>
  api(`/videos/${id}/progress`, { body: { position_seconds: Math.floor(position), watched_seconds: Math.min(600, Math.floor(watched)) } });

export const useSearch = (q: string, type: string) =>
  useQuery({
    queryKey: qk.search(q, type),
    queryFn: () => api<{ counts: Record<string, number>; data: T.SearchResult[] }>(`/search?q=${encodeURIComponent(q)}&type=${type}`),
    enabled: q.trim().length >= 2,
  });
export const useImageBank = () =>
  useQuery({ queryKey: qk.images, queryFn: () => api<{ total: number; types: Record<string, number>; data: { question_id: number; url: string; caption: string | null; type: string | null; department: string | null; locked: boolean }[] }>('/image-bank') });
export const useLabValues = () => useQuery({ queryKey: qk.labs, queryFn: () => api<{ data: { category: string; items: { name: string; range: string; unit: string | null }[] }[] }>('/tools/lab-values'), staleTime: 24 * 3600_000 });
export const useDrugs = () => useQuery({ queryKey: qk.drugs, queryFn: () => api<{ disclaimer: string; data: { id: number; name: string; category: string; dose: string; watch: string | null }[] }>('/tools/drugs'), staleTime: 24 * 3600_000 });

// ---------- Discussion ----------

export const useComments = (questionId: number, sort: 'top' | 'new') =>
  useInfiniteQuery({
    queryKey: qk.comments(questionId, sort),
    queryFn: ({ pageParam }) =>
      api<{ question: { id: number; code: string; stem: string }; count: number; verified: T.Comment | null; data: T.Comment[]; meta: { current_page: number; last_page: number } }>(
        `/questions/${questionId}/comments?sort=${sort}&page=${pageParam}`,
      ),
    initialPageParam: 1,
    getNextPageParam: (last) => (last.meta.current_page < last.meta.last_page ? last.meta.current_page + 1 : undefined),
  });
export const postComment = (questionId: number, body: string, kind?: 'doubt' | 'tip', parentId?: number) =>
  api<T.Comment>(`/questions/${questionId}/comments`, { body: { body, kind, parent_id: parentId } });
export const voteComment = (id: number) => api<{ voted: boolean; votes: number }>(`/comments/${id}/vote`, { method: 'POST' });
export const flagComment = (id: number, reason: string) => api(`/comments/${id}/flag`, { body: { reason } });

// ---------- Exams ----------

export const useExams = () => useQuery({ queryKey: qk.exams, queryFn: () => api<{ data: T.Exam[] }>('/exams') });
export const useLeaderboard = (id: number | undefined, scope: string) =>
  useQuery({ queryKey: qk.leaderboard(id ?? 0, scope), queryFn: () => api<T.Leaderboard>(`/exams/${id}/leaderboard?scope=${scope}`), enabled: !!id });
export function useRegisterExam() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => api<{ registered: boolean; registrations: number }>(`/exams/${id}/register`, { method: 'POST' }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: qk.exams });
      qc.invalidateQueries({ queryKey: qk.home });
    },
  });
}

// ---------- Match predictor ----------

export const useMatchProfile = () => useQuery({ queryKey: qk.matchProfile, queryFn: () => api<T.MatchProfile>('/match/profile') });
export function useUpdateMatchProfile() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (body: Partial<{ raw_score: number; is_female: boolean; has_managerial: boolean; specialty_ids: number[]; remind: boolean }>) =>
      api<{ final_score: number | null; specialty_ids: number[] }>('/match/profile', { method: 'PATCH', body }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['match'] });
    },
  });
}
export const useSpecialties = () => useQuery({ queryKey: qk.specialties, queryFn: () => api<{ score: number; max: number; data: T.SpecialtyCard[] }>('/match/specialties') });
export const useChances = (id: number) => useQuery({ queryKey: qk.chances(id), queryFn: () => api<T.Chances>(`/match/specialties/${id}/chances`), enabled: !!id });
export function useSaveChoices() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ specialtyId, universityIds }: { specialtyId: number; universityIds: number[] }) =>
      api<T.ChoiceList>(`/match/specialties/${specialtyId}/choices`, { method: 'PUT', body: { university_ids: universityIds } }),
    onSuccess: (list, vars) => {
      qc.setQueryData(qk.choices, list);
      qc.invalidateQueries({ queryKey: qk.chances(vars.specialtyId) });
    },
  });
}
export const useChoices = () => useQuery({ queryKey: qk.choices, queryFn: () => api<T.ChoiceList>('/match/choices') });

// ---------- Study plan ----------

export const useStudyPlan = (date?: string) => useQuery({ queryKey: qk.plan(date), queryFn: () => api<T.StudyPlan>(`/study-plan${date ? `?date=${date}` : ''}`) });
export function useCreatePlan() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (body: { exam_date: string; daily_minutes?: number; exam_label?: string }) => api('/study-plan', { body }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['study-plan'] });
      qc.invalidateQueries({ queryKey: qk.home });
    },
  });
}
export const toggleTask = (id: number) => api<{ done: boolean }>(`/study-plan/tasks/${id}/toggle`, { method: 'POST' });
export const rebalancePlan = () => api<{ moved: number; message: string }>('/study-plan/rebalance', { method: 'POST' });

// ---------- Notifications ----------

export const useNotifications = () => useQuery({ queryKey: qk.notifications, queryFn: () => api<T.Paginated<T.AppNotification> & { unread: number }>('/notifications') });
export const markRead = (id: string) => api(`/notifications/${id}/read`, { method: 'POST' });
export const markAllRead = () => api('/notifications/read-all', { method: 'POST' });
export const deleteNotification = (id: string) => api(`/notifications/${id}`, { method: 'DELETE' });
export const sendTestNotification = () => api<{ sent: boolean; has_push_device: boolean }>('/notifications/test', { method: 'POST' });
export const useNotificationSettings = () => useQuery({ queryKey: qk.notificationSettings, queryFn: () => api<T.NotificationSettings>('/notification-settings') });
export function useUpdateNotificationSettings() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (body: Partial<Omit<T.NotificationSettings, 'categories' | 'has_push_device'>> & { categories?: Partial<T.NotificationSettings['categories']> }) =>
      api<T.NotificationSettings>('/notification-settings', { method: 'PATCH', body }),
    onSuccess: (data) => {
      qc.setQueryData(qk.notificationSettings, data);
      qc.invalidateQueries({ queryKey: qk.home });
    },
  });
}
