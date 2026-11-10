import { create } from 'zustand';

export type Toast = { id: string; title: string; body?: string; category?: string; type?: string; href?: string | null };

type ToastState = { current: Toast | null; show: (t: Toast) => void; hide: () => void };

/** One in-app banner at a time, e.g. a notification that arrives while the app is open. */
export const useToast = create<ToastState>((set) => ({
  current: null,
  show: (current) => set({ current }),
  hide: () => set({ current: null }),
}));
