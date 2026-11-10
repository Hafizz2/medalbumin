import AsyncStorage from '@react-native-async-storage/async-storage';
import * as Crypto from 'expo-crypto';
import * as FileSystem from 'expo-file-system/legacy';
import { create } from 'zustand';
import { createJSONStorage, persist } from 'zustand/middleware';
import { api } from '../api/client';

export type PackInfo = { id: number; name: string; questions: number; notes: number; size_kb: number; version: string };

export type OfflineQuestion = {
  id: number;
  code: string | null;
  subcategory_id: number | null;
  difficulty: 'easy' | 'moderate' | 'hard';
  year: number | null;
  stem: string;
  image: { url: string; caption: string | null } | null;
  options: { key: string; text: string }[];
  correct: string;
  explanation: string | null;
};

export type OfflineNote = { id: number; title: string; subcategory_id: number | null; read_minutes: number; body_html: string };

export type OfflinePack = {
  id: number;
  name: string;
  locale: string;
  generated_at: string;
  subcategories: { id: number; name: string }[];
  questions: OfflineQuestion[];
  notes: OfflineNote[];
};

export type Downloaded = { id: number; name: string; version: string; questions: number; notes: number; bytes: number; downloaded_at: string };

type Attempt = { question_id: number; option: string; time_ms: number; answered_at: string };

type OfflineState = {
  packs: Record<number, Downloaded>;
  /** Offline answers waiting to be uploaded. */
  queue: Attempt[];
  /** The batch being uploaded; kept until the server confirms so retries are never counted twice. */
  sending: { id: string; attempts: Attempt[] } | null;
  /** question id → answered correctly, for "unseen first" and per-pack progress. */
  answered: Record<number, boolean>;
  setPack: (p: Downloaded) => void;
  dropPack: (id: number) => void;
  record: (a: Attempt, correct: boolean) => void;
};

export const useOffline = create<OfflineState>()(
  persist(
    (set) => ({
      packs: {},
      queue: [],
      sending: null,
      answered: {},
      setPack: (p) => set((s) => ({ packs: { ...s.packs, [p.id]: p } })),
      dropPack: (id) =>
        set((s) => {
          const packs = { ...s.packs };
          delete packs[id];
          return { packs };
        }),
      record: (a, correct) => set((s) => ({ queue: [...s.queue, a], answered: { ...s.answered, [a.question_id]: correct } })),
    }),
    { name: 'medalbumin.offline', storage: createJSONStorage(() => AsyncStorage) },
  ),
);

const DIR = `${FileSystem.documentDirectory}offline/`;
const fileFor = (id: number) => `${DIR}pack-${id}.json`;

// Parsed packs stay in memory while the app is open.
const memory = new Map<number, OfflinePack>();

export async function downloadPack(info: PackInfo): Promise<void> {
  const pack = await api<OfflinePack>(`/offline/packs/${info.id}`);
  const json = JSON.stringify(pack);
  await FileSystem.makeDirectoryAsync(DIR, { intermediates: true }).catch(() => {});
  await FileSystem.writeAsStringAsync(fileFor(info.id), json);
  memory.set(info.id, pack);
  useOffline.getState().setPack({
    id: info.id,
    name: pack.name,
    version: info.version,
    questions: pack.questions.length,
    notes: pack.notes.length,
    bytes: json.length,
    downloaded_at: new Date().toISOString(),
  });
}

export async function removePack(id: number): Promise<void> {
  memory.delete(id);
  await FileSystem.deleteAsync(fileFor(id), { idempotent: true }).catch(() => {});
  useOffline.getState().dropPack(id);
}

export async function loadPack(id: number): Promise<OfflinePack | null> {
  const cached = memory.get(id);
  if (cached) return cached;
  try {
    const pack = JSON.parse(await FileSystem.readAsStringAsync(fileFor(id))) as OfflinePack;
    memory.set(id, pack);
    return pack;
  } catch {
    // The file is gone (e.g. storage cleared) — forget the pack so it can be downloaded again.
    useOffline.getState().dropPack(id);
    return null;
  }
}

export function recordAttempt(questionId: number, option: string, correct: boolean, timeMs: number) {
  useOffline.getState().record({ question_id: questionId, option, time_ms: Math.round(timeMs), answered_at: new Date().toISOString() }, correct);
}

let syncing = false;

/** Uploads offline answers in batches. Safe to call any time; does nothing when there is nothing to send. */
export async function syncAttempts(): Promise<number> {
  if (syncing) return 0;
  syncing = true;
  let sent = 0;
  try {
    for (;;) {
      let { sending, queue } = useOffline.getState();
      if (!sending) {
        if (!queue.length) break;
        sending = { id: Crypto.randomUUID(), attempts: queue.slice(0, 500) };
        useOffline.setState({ sending, queue: queue.slice(500) });
      }
      await api('/offline/sync', { body: { batch_id: sending.id, attempts: sending.attempts } });
      sent += sending.attempts.length;
      useOffline.setState({ sending: null });
    }
  } catch {
    // Still offline or the server is unreachable — try again on the next reconnect.
  } finally {
    syncing = false;
  }
  return sent;
}

export const pendingCount = (s: Pick<OfflineState, 'queue' | 'sending'>) => s.queue.length + (s.sending?.attempts.length ?? 0);
