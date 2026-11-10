// Shapes returned by the MedAlbumin API (backend/app/Http/Controllers/Api/V1).

export type User = {
  id: number;
  name: string | null;
  initials: string;
  phone: string | null;
  email: string | null;
  avatar_url: string | null;
  gender: 'female' | 'male' | null;
  university: { id: number; name: string } | null;
  program: string | null;
  study_year: string | null;
  target_exam: 'exit' | 'qualification' | 'licensing' | 'residency' | null;
  exam_date: string | null;
  target_score: number;
  app_locale: string;
  content_locale: string;
  dark_mode: boolean;
  daily_reminder: boolean;
  reminder_time: string;
  is_guest: boolean;
  profile_complete: boolean;
  referral_code: string | null;
  premium: { active: boolean; plan: string | null; until: string | null };
  free_questions_left: number | null;
  created_at: string;
};

export type AuthResponse = { token: string; is_new: boolean; user: User };

export type DeviceLimitError = {
  code: 'DEVICE_LIMIT';
  limit: number;
  devices: { id: number; name: string | null; platform: string; last_seen_at: string | null }[];
};

export type Language = { code: string; name: string; native_name: string; direction: 'ltr' | 'rtl'; ui_version: string };

export type AppConfig = {
  languages: Language[];
  default_language: string;
  min_app_version: string;
  auth: { telegram_login: boolean; telegram_login_url: string; phone_code_via: 'telegram' | 'log'; apple: boolean; email?: boolean };
  support: { telegram: string; email: string };
  legal: { terms_url: string; privacy_url: string; delete_account_url: string };
  counts: { questions: number; papers: number };
  guest_free_questions: number;
};

export type Home = {
  greeting: 'morning' | 'afternoon' | 'evening';
  first_name: string | null;
  initials: string;
  avatar_url: string | null;
  readiness: { score: number; status: 'getting_started' | 'on_track' | 'needs_work'; trend: number | null; weak_areas: string[]; exam_label: string; days_to_exam: number | null };
  stats: { answered: number; accuracy: number; streak: number };
  today: { answered: number; goal: number; studied: boolean };
  week: { date: string; studied: boolean }[];
  study_plan: { sessions: number; done: number; minutes: number } | null;
  counts: { cards_due: number; mistakes: number };
  next_exam: { id: number; title: string; starts_at: string | null; registered: boolean } | null;
  unread_notifications: number;
};

export type DepartmentSummary = {
  id: number;
  name: string;
  short_name: string | null;
  icon: string;
  color_bg: string;
  color_ink: string;
  questions: number;
  answered: number;
  topics_total: number;
  topics_done: number;
  done_pct: number;
  accuracy: number | null;
  status: 'new' | 'prog' | 'done';
};

export type Subcategory = {
  id: number;
  name: string;
  questions: number;
  accuracy: number | null;
  answered: number;
  videos: number;
  notes: number;
  topics: { id: number; name: string }[];
};

export type DepartmentDetail = {
  id: number;
  name: string;
  icon: string;
  color_bg: string;
  color_ink: string;
  questions: number;
  done_pct: number;
  accuracy: number | null;
  subcategories: Subcategory[];
};

export type Paper = {
  id: number;
  name: string;
  year: number | null;
  ec_label: string | null;
  code: string | null;
  description: string | null;
  questions: number;
  time_limit_minutes: number | null;
  locked: boolean;
  best_pct: number | null;
  done_pct: number;
  in_progress: boolean;
};

export type PaperGroup = { code: string; name: string; papers_count: number; questions: number; best_pct: number | null; done_pct: number; papers: Paper[] };

export type BuilderOptions = {
  departments: { id: number; name: string; short_name: string | null; questions: number; subcategories: { id: number; name: string; questions: number }[] }[];
  sizes: number[];
  seconds_per_question: number;
  pools: { id: 'unused' | 'wrong' | 'bookmarked' | 'all'; count: number }[];
  locked_questions: number;
};

export type QuestionOption = { key: string; label: string; text: string; pick_pct: number | null };

export type Question = {
  id: number;
  code: string;
  locale: string;
  available_locales: string[];
  department: { id: number; name: string; short_name: string | null } | null;
  subcategory: { id: number; name: string } | null;
  breadcrumb: string;
  source: string | null;
  difficulty: 'easy' | 'moderate' | 'hard';
  stem: string;
  image: { url: string; caption: string | null; type: string | null } | null;
  options: QuestionOption[];
  comments_count: number;
  bookmarked: boolean | null;
  selected: string | null;
  // Present only once revealed:
  correct_option?: string;
  explanation?: string | null;
  explanations?: Record<string, string>;
  correct_pct?: number | null;
};

export type QuizSession = {
  id: number;
  type: 'practice' | 'past_paper' | 'exam' | 'review' | 'daily';
  mode: 'practice' | 'timed';
  title: string;
  status: 'in_progress' | 'completed' | 'abandoned';
  exam_id: number | null;
  paper_id: number | null;
  question_count: number;
  answered_count: number;
  correct_count: number | null;
  score_pct: number | null;
  time_limit_seconds: number | null;
  time_spent_seconds: number;
  started_at: string | null;
  deadline: string | null;
  seconds_left: number | null;
  completed_at: string | null;
  allow_back: boolean;
};

export type SessionQuestion = { position: number; answered: boolean; is_correct: boolean | null; is_flagged: boolean; question: Question };

export type SessionPayload = { session: QuizSession; questions: SessionQuestion[] };

export type AnswerResponse = { answered_count: number; selected: string | null; is_correct?: boolean; correct_count?: number; question?: Question };

export type QuizResult = {
  score_pct: number;
  correct: number;
  total: number;
  answered: number;
  wrong: number;
  passed: boolean;
  pass_mark: number;
  delta: number | null;
  rank: number | null;
  takers: number | null;
  top_pct: number | null;
  time_spent_seconds: number;
  time_limit_seconds: number | null;
  seconds_per_question: number;
  by_system: { name: string; department_id: number | null; subcategory_id: number | null; correct: number; total: number; pct: number }[];
};

export type ProgressOverview = {
  totals: { answered: number; distinct_answered: number; accuracy: number; study_seconds: number; streak: number };
  trend: { week: string; accuracy: number | null }[];
  accuracy_delta: number | null;
  questions_delta_pct: number | null;
  study_delta_pct: number | null;
  average_score: number | null;
  target_score: number;
  top_subjects: SubjectRow[];
  last_seven_days: { date: string; questions: number }[];
};

export type SubjectRow = { id: number; name: string; icon: string; color_bg: string; color_ink: string; accuracy: number; answered: number };

export type MockRow = { id: number; title: string; type: string; completed_at: string | null; score_pct: number; correct: number; total: number; time_spent_seconds: number; top_pct: number | null; level: string };

export type Deck = { id: number; title: string; path: string; cards: number; due: number; new: number; is_mine: boolean };

export type Flashcard = {
  id: number;
  deck_title: string;
  path: string;
  front: string;
  prompt: string | null;
  back: string;
  detail: string | null;
  is_new: boolean;
  buttons: { rating: 1 | 2 | 3 | 4; label: string; interval: string }[];
};

export type OfficialNoteRow = { id: number; title: string; breadcrumb: string; read_minutes: number; is_new: boolean; locked: boolean; published_at: string | null };

export type MyNote = {
  id: number;
  title: string | null;
  body: string;
  subcategory: { id: number; name: string; department: string | null } | null;
  context: { type: 'note' | 'question' | 'video'; id: number; title: string } | null;
  video_seconds: number | null;
  updated_at: string;
};

export type NoteDetail = {
  id: number;
  title: string;
  body_html: string;
  locale: string;
  available_locales: string[];
  bodies: Record<string, string>;
  breadcrumb: string;
  read_minutes: number;
  attachment_url: string | null;
  department_id: number;
  subcategory: { id: number; name: string; questions: number } | null;
  my_notes: MyNote[];
};

export type VideoCard = {
  id: number;
  title: string;
  breadcrumb: string;
  lecturer: string | null;
  duration_seconds: number;
  thumbnail_url: string | null;
  views: number;
  access: 'free' | 'premium';
  locked: boolean;
  position_seconds: number;
  completed: boolean;
};

export type VideoDetail = VideoCard & {
  description: string | null;
  streams: { quality: string; url: string; youtube_id?: string }[];
  captions: Record<string, string>;
  chapters: { start_seconds: number; title: string }[];
  linked_question_ids: number[];
  my_notes: { id: number; body: string; video_seconds: number | null }[];
};

export type SearchResult = { type: 'question' | 'note' | 'card' | 'video'; id: number; deck_id?: number; code: string; title: string; meta: string; locked: boolean };

export type Comment = {
  id: number;
  kind: 'doubt' | 'tip' | 'reply';
  body: string;
  author: { name: string; initials: string; role: 'faculty' | null };
  is_mine: boolean;
  is_verified: boolean;
  votes: number;
  voted: boolean;
  created_at: string;
  replies?: Comment[];
};

export type Exam = {
  id: number;
  title: string;
  description: string | null;
  type: 'grand' | 'past_paper' | 'department' | 'daily';
  status: string;
  starts_at: string | null;
  duration_minutes: number;
  question_count: number;
  registrations: number;
  registered: boolean;
  is_open: boolean;
  can_take: boolean;
  access: 'all' | 'premium' | 'university';
  leaderboard: boolean;
  my_session_id: number | null;
};

export type Leaderboard = {
  exam: { id: number; title: string };
  me: { rank: number; takers: number; top_pct: number | null; score_pct: number; places_moved: number | null; session_id: number } | null;
  data: { rank: number; name: string; initials: string; university: string | null; score_pct: number; is_me: boolean }[];
};

export type MatchRules = {
  female_bonus_pct: number;
  managerial_bonus_pct: number;
  max_specialties: number;
  max_universities: number;
  disclaimer: string;
  timeline: string[];
  timeline_step: number;
};

export type MatchProfile = {
  raw_score: number | null;
  is_female: boolean;
  has_managerial: boolean;
  final_score: number | null;
  specialty_ids: number[];
  remind: boolean;
  predicted: { score: number; from_mocks: number } | null;
  rules: MatchRules;
  has_data: boolean;
};

export type SpecialtyCard = { id: number; code: string; name: string; competition: 'low' | 'medium' | 'high' | 'very_high'; lowest_matched: number | null; seats: number; above_last_year: boolean | null; gap: number | null };

export type Chances = {
  years: number[];
  universities: { university_id: number; name: string; short_name: string | null; seats: number; cutoffs: (number | null)[]; direction: 'up' | 'down' | 'flat'; chance: number; tier: 'likely' | 'possible' | 'reach' }[];
  likely: number;
  others: number;
  more_likely_with_3: number;
  specialty: { id: number; name: string };
  score: number;
  picked: number[];
  max: number;
  disclaimer: string;
};

export type ChoiceList = {
  score: number;
  overall_chance: number;
  groups: { order: number; specialty: { id: number; name: string }; has_safe_option: boolean; items: { university_id: number; name: string; rank: number; chance: number; tier: string }[] }[];
  timeline: { title: string; done: boolean }[];
  remind: boolean;
  disclaimer: string;
};

export type StudyPlan = {
  plan: null | { id: number; exam_label: string | null; exam_date: string; day: number; total_days: number; phase: 1 | 2 | 3; daily_minutes: number };
  date?: string;
  week?: { date: string; label: string; day: number; status: 'done' | 'missed' | 'today' | 'mock' | '' }[];
  missed_days?: number;
  tasks?: { id: number; kind: 'questions' | 'read' | 'video' | 'cards' | 'mock'; title: string; meta: string | null; minutes: number; target: Record<string, unknown> | null; done: boolean }[];
  minutes?: number;
};

export type NotificationCategory = 'reminders' | 'exams' | 'content' | 'community' | 'updates' | 'promos' | 'account';

export type AppNotification = {
  id: string;
  type: string;
  category: NotificationCategory;
  title: string;
  body: string;
  route: string | null;
  params: Record<string, unknown> | null;
  vars: Record<string, string | number | null> | null;
  image: string | null;
  action: string | null;
  read: boolean;
  created_at: string;
};

export type Paginated<T> = { data: T[]; meta: { current_page: number; last_page: number; total?: number } };

export type NotificationSettings = {
  categories: Record<Exclude<NotificationCategory, 'account'>, boolean>;
  daily_reminder: boolean;
  reminder_time: string;
  daily_goal: number;
  has_push_device: boolean;
};
