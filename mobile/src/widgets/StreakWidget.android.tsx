import { Box, Column, Row, Spacer, Text } from '@expo/ui/jetpack-compose';
import { background, cornerRadius, fillMaxSize, height, padding, size, width } from '@expo/ui/jetpack-compose/modifiers';
import { createWidget, type WidgetEnvironment } from 'expo-widgets';
import type { StreakWidgetProps } from './types';

/**
 * Android home screen widget (Jetpack Glance via Expo UI). Resizable from 2×1 up to the full screen.
 *
 * Runs in the widget's own runtime: no hooks, no outside variables — everything it needs comes in
 * `props` and `env`.
 *
 * Android widgets cannot use weights or "space between", so every piece is sized from the widget's
 * real size. That size arrives as `env.widgetSize` (dp), added by patches/expo-widgets+*.patch.
 * Without the patch the widget still works, laid out for a 2×2 cell.
 */
const StreakWidget = (props: StreakWidgetProps, env: WidgetEnvironment) => {
  'widget';
  const navy = '#062A5E';
  const panel = '#0D3A7A';
  const track = '#1D4C8F';
  const teal = '#14BCC4';
  const soft = '#A9BBD8';
  const amber = '#FBBF77';
  const flame = '#FF9F43';
  const white = '#FFFFFF';

  // The widget can be drawn before the app has sent any numbers.
  const p = props ?? ({} as StreakWidgetProps);
  const l = p.l ?? ({} as StreakWidgetProps['l']);
  const streak = p.streak ?? 0;
  const goal = p.goal && p.goal > 0 ? p.goal : 20;
  const answered = Math.max(0, Math.min(p.answered ?? 0, goal));
  const studied = !!p.studiedToday;
  const week = p.week ?? '0000000';
  const letters = p.weekLetters ?? 'MTWTFSS';
  const hasExam = p.daysToExam !== null && p.daysToExam !== undefined && p.daysToExam >= 0;

  const clamp = (v: number, lo: number, hi: number) => Math.max(lo, Math.min(hi, v));
  const ws = (env as { widgetSize?: { width: number; height: number } } | undefined)?.widgetSize;
  const known = !!ws && ws.width > 0 && ws.height > 0;
  const W = known ? Math.floor(ws!.width) : 170;
  const H = known ? Math.floor(ws!.height) : 170;
  const pad = W < 220 || H < 120 ? 12 : 16;
  const iw = W - pad * 2;
  const ih = H - pad * 2;
  const frame = known ? size(W, H) : fillMaxSize();

  const goalText = `${answered}/${goal}`;
  const statusText = studied ? (l.studied ?? 'Studied today') : (l.keepIt ?? 'Study today to keep it');
  const statusColor = studied ? teal : amber;
  const examText = hasExam ? `${l.daysTo ?? 'days to'} ${p.examLabel ?? ''}` : (l.noExam ?? 'Set your exam date');

  const vgap = (n: number) => (n > 0 ? <Spacer modifiers={[height(n)]} /> : null);
  const hgap = (n: number) => (n > 0 ? <Spacer modifiers={[width(n)]} /> : null);

  /** Rounded progress bar of an exact width. */
  const bar = (w: number) => {
    const done = answered >= goal;
    const fill = answered > 0 ? clamp(Math.round((w * answered) / goal), 6, w) : 0;
    return (
      <Box modifiers={[size(w, 6), background(track), cornerRadius(3)]}>
        {fill > 0 ? <Box modifiers={[size(fill, 6), background(done ? teal : flame), cornerRadius(3)]} /> : null}
      </Box>
    );
  };

  /** Flame and the streak number. */
  const head = (num: number) => (
    <Row verticalAlignment="center">
      <Text style={{ fontSize: Math.round(num * 0.62) }}>🔥</Text>
      {hgap(Math.round(num * 0.16))}
      <Text color={white} maxLines={1} style={{ fontSize: num, fontWeight: 'bold' }}>
        {`${streak}`}
      </Text>
    </Row>
  );

  /** The last 7 days as round cells that together span exactly `w`. */
  const weekRow = (w: number, cell: number) => {
    const gap = Math.floor((w - cell * 7) / 6);
    return (
      <Row verticalAlignment="center">
        {[0, 1, 2, 3, 4, 5, 6].map((i) => {
          const on = week[i] === '1';
          const today = i === 6;
          const bg = on ? (today ? flame : teal) : today ? '#2B5596' : '#123F80';
          const fg = on ? navy : today ? white : soft;
          return (
            <Row key={i} verticalAlignment="center">
              {i > 0 ? hgap(gap) : null}
              <Box contentAlignment="center" modifiers={[size(cell, cell), background(bg), cornerRadius(Math.round(cell / 2))]}>
                {cell >= 15 ? (
                  <Text color={fg} maxLines={1} style={{ fontSize: Math.max(7, Math.round(cell * 0.42)), fontWeight: 'bold' }}>
                    {letters[i] ?? ''}
                  </Text>
                ) : null}
              </Box>
            </Row>
          );
        })}
      </Row>
    );
  };

  // ── Not signed in yet ────────────────────────────────────────────────────────────────────────
  if (!p.signedIn) {
    return (
      <Box contentAlignment="center" modifiers={[frame, background(navy), cornerRadius(24)]}>
        <Column horizontalAlignment="center" modifiers={[width(iw)]}>
          <Text style={{ fontSize: ih < 90 ? 20 : 30 }}>🔥</Text>
          {vgap(4)}
          <Text color="#F3F6FA" maxLines={2} style={{ fontSize: 13, fontWeight: 'bold', textAlign: 'center' }}>
            {l.signIn ?? 'Open MedAlbumin to start'}
          </Text>
        </Column>
      </Box>
    );
  }

  // ── Strip: one row high (2×1, 4×1, 5×1) ──────────────────────────────────────────────────────
  if (ih < 84) {
    const num = clamp(Math.round(ih * 0.52), 18, 34);
    const split = iw >= 230;
    const rightW = split ? clamp(Math.round(iw * 0.36), 84, 150) : 0;
    const leftW = iw - rightW - (split ? 12 : 0);
    return (
      <Box contentAlignment="centerStart" modifiers={[frame, background(navy), cornerRadius(24)]}>
        <Row verticalAlignment="center" modifiers={[padding(pad, 0, pad, 0)]}>
          <Row verticalAlignment="center" modifiers={[width(leftW)]}>
            {head(num)}
            {hgap(10)}
            <Column>
              <Text color={white} maxLines={1} style={{ fontSize: 13, fontWeight: 'bold' }}>
                {l.dayStreak ?? 'day streak'}
              </Text>
              {ih >= 44 ? (
                <Text color={statusColor} maxLines={1} style={{ fontSize: 11 }}>
                  {statusText}
                </Text>
              ) : null}
            </Column>
          </Row>
          {split ? hgap(12) : null}
          {split ? (
            <Column modifiers={[width(rightW)]}>
              <Text color={white} maxLines={1} style={{ fontSize: 13, fontWeight: 'bold' }}>
                {`${l.today ?? 'Today'} ${goalText}`}
              </Text>
              {vgap(5)}
              {bar(rightW)}
            </Column>
          ) : null}
        </Row>
      </Box>
    );
  }

  // ── Wide: two columns, week along the bottom (4×2 and larger) ───────────────────────────────
  if (iw >= 230) {
    const colGap = 12;
    const leftW = Math.floor(iw * 0.4);
    const rightW = iw - leftW - colGap;
    const inner = rightW - 24;

    // Right panel: today's goal, then the exam countdown when there is room.
    const goalH = 15 + 28 + 5 + 6;
    const examH = 10 + 26 + 15;
    // The week row comes first (4×2); the exam countdown joins once both fit (4×3 and up).
    const showExam = ih >= goalH + examH + 24 + 12 + 22;
    const panelH = goalH + (showExam ? examH : 0) + 24;

    // Week row takes what is left, as long as the cells stay readable.
    const cellMax = Math.min(34, Math.floor((iw - 6 * 6) / 7));
    const cell = clamp(ih - panelH - 12, 0, cellMax);
    const showWeek = cell >= 18;
    const topH = showWeek ? ih - cell - 12 : ih;
    const panelFinal = Math.min(topH, panelH);
    const num = clamp(Math.round(Math.min(leftW * 0.4, topH * 0.4)), 28, 64);
    const leftH = Math.round(num * 1.25) + 17 + 6 + 15;

    return (
      <Box modifiers={[frame, background(navy), cornerRadius(24)]}>
        <Column modifiers={[padding(pad, pad, pad, pad)]}>
          <Row verticalAlignment="center" modifiers={[height(topH)]}>
            <Column modifiers={[width(leftW)]}>
              {head(num)}
              <Text color={soft} maxLines={1} style={{ fontSize: 13, fontWeight: 'bold' }}>
                {l.dayStreak ?? 'day streak'}
              </Text>
              {topH >= leftH ? vgap(6) : null}
              {topH >= leftH ? (
                <Text color={statusColor} maxLines={1} style={{ fontSize: 11 }}>
                  {statusText}
                </Text>
              ) : null}
            </Column>
            {hgap(colGap)}
            <Box contentAlignment="centerStart" modifiers={[size(rightW, panelFinal), background(panel), cornerRadius(18)]}>
              <Column modifiers={[padding(12, 0, 12, 0)]}>
                <Text color={soft} maxLines={1} style={{ fontSize: 11, fontWeight: 'bold' }}>
                  {l.today ?? 'Today'}
                </Text>
                <Text color={answered >= goal ? teal : white} maxLines={1} style={{ fontSize: 22, fontWeight: 'bold' }}>
                  {goalText}
                </Text>
                {vgap(5)}
                {bar(inner)}
                {showExam ? vgap(10) : null}
                {showExam ? (
                  <Text color={white} maxLines={1} style={{ fontSize: 20, fontWeight: 'bold' }}>
                    {hasExam ? `${p.daysToExam}` : '—'}
                  </Text>
                ) : null}
                {showExam ? (
                  <Text color={soft} maxLines={1} style={{ fontSize: 11 }}>
                    {examText}
                  </Text>
                ) : null}
              </Column>
            </Box>
          </Row>
          {showWeek ? vgap(12) : null}
          {showWeek ? weekRow(iw, cell) : null}
        </Column>
      </Box>
    );
  }

  // ── Compact: one column (2×2, 2×3, 3×2 …) ───────────────────────────────────────────────────
  const num = clamp(Math.round(Math.min(iw * 0.3, ih * 0.34)), 26, 60);
  const headH = Math.round(num * 1.25) + 17;
  const goalH = 16 + 5 + 6;
  const statusH = 15;
  const examH = 15;
  const cell = Math.min(30, Math.floor((iw - 6 * 4) / 7));
  const minGap = 7;

  // Add sections in order of importance while they still fit.
  let used = headH;
  let parts = 1;
  const fits = (h: number) => ih - used >= h + minGap;
  const showGoal = fits(goalH);
  if (showGoal) {
    used += goalH + minGap;
    parts += 1;
  }
  const showWeek = cell >= 14 && fits(cell);
  if (showWeek) {
    used += cell + minGap;
    parts += 1;
  }
  const showStatus = fits(statusH);
  if (showStatus) {
    used += statusH + minGap;
    parts += 1;
  }
  const showExam = hasExam && fits(examH);
  if (showExam) {
    used += examH + minGap;
    parts += 1;
  }

  // Share the leftover height between the sections, and centre what remains.
  const free = ih - used;
  const gap = minGap + (parts > 1 ? Math.min(14, Math.floor(free / (parts - 1))) : 0);
  const top = Math.max(0, Math.floor((free - (gap - minGap) * (parts - 1)) / 2));

  return (
    <Box modifiers={[frame, background(navy), cornerRadius(24)]}>
      <Column modifiers={[padding(pad, pad + top, pad, 0)]}>
        {head(num)}
        <Text color={soft} maxLines={1} style={{ fontSize: 13, fontWeight: 'bold' }}>
          {l.dayStreak ?? 'day streak'}
        </Text>
        {showWeek ? vgap(gap) : null}
        {showWeek ? weekRow(iw, cell) : null}
        {showGoal ? vgap(gap) : null}
        {showGoal ? (
          <Text color={answered >= goal ? teal : white} maxLines={1} style={{ fontSize: 12, fontWeight: 'bold' }}>
            {`${l.today ?? 'Today'} · ${goalText}`}
          </Text>
        ) : null}
        {showGoal ? vgap(5) : null}
        {showGoal ? bar(iw) : null}
        {showStatus ? vgap(gap) : null}
        {showStatus ? (
          <Text color={statusColor} maxLines={1} style={{ fontSize: 11 }}>
            {statusText}
          </Text>
        ) : null}
        {showExam ? vgap(gap) : null}
        {showExam ? (
          <Text color={soft} maxLines={1} style={{ fontSize: 11 }}>
            {`${p.daysToExam} ${examText}`}
          </Text>
        ) : null}
      </Column>
    </Box>
  );
};

export default createWidget('StreakWidget', StreakWidget);
