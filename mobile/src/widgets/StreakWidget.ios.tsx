import { HStack, Image, Spacer, Text, VStack } from '@expo/ui/swift-ui';
import { containerBackground, font, foregroundStyle, frame, lineLimit, minimumScaleFactor, widgetURL } from '@expo/ui/swift-ui/modifiers';
import { createWidget, type WidgetEnvironment } from 'expo-widgets';
import type { StreakWidgetProps } from './types';

/**
 * iOS home screen + lock screen widget (SwiftUI via Expo UI).
 * Runs in the widget's own runtime: no hooks, no outside variables — everything it needs comes in `p`.
 */
const StreakWidget = (p: StreakWidgetProps, env: WidgetEnvironment) => {
  'widget';
  const navy = '#062A5E';
  const teal = '#14BCC4';
  const soft = '#A9BBD8';
  const flame = p.studiedToday ? '#FF9F43' : '#7F93B5';
  const family = env.widgetFamily ?? 'systemSmall';
  const goalText = `${Math.min(p.answered, p.goal)}/${p.goal}`;
  const examLine = p.daysToExam !== null && p.daysToExam >= 0 ? `${p.daysToExam} ${p.l.daysTo} ${p.examLabel}` : p.l.noExam;

  if (!p.signedIn) {
    return (
      <VStack spacing={6} modifiers={[containerBackground(navy, 'widget'), widgetURL('medalbumin://')]}>
        <Image systemName="flame.fill" size={28} color={teal} />
        <Text modifiers={[font({ size: 13, weight: 'semibold' }), foregroundStyle('#F3F6FA')]}>{p.l.signIn}</Text>
      </VStack>
    );
  }

  // Lock screen: round.
  if (family === 'accessoryCircular') {
    return (
      <VStack spacing={0} modifiers={[widgetURL('medalbumin://')]}>
        <Image systemName="flame.fill" size={14} />
        <Text modifiers={[font({ size: 18, weight: 'bold', design: 'rounded' })]}>{`${p.streak}`}</Text>
      </VStack>
    );
  }

  // Lock screen: wide.
  if (family === 'accessoryRectangular') {
    return (
      <VStack alignment="leading" spacing={2} modifiers={[widgetURL('medalbumin://')]}>
        <HStack spacing={4}>
          <Image systemName="flame.fill" size={13} />
          <Text modifiers={[font({ size: 15, weight: 'bold' })]}>{`${p.streak} ${p.l.dayStreak}`}</Text>
        </HStack>
        <Text modifiers={[font({ size: 12 }), lineLimit(1)]}>{p.studiedToday ? `${p.l.today} ${goalText}` : p.l.keepIt}</Text>
        <Text modifiers={[font({ size: 12 }), lineLimit(1)]}>{examLine}</Text>
      </VStack>
    );
  }

  const dot = (on: string, isToday: boolean) => (on === '1' ? (isToday ? '#FF9F43' : teal) : '#2B4C7E');
  const days = [0, 1, 2, 3, 4, 5, 6];

  const streakBlock = (
    <VStack alignment="leading" spacing={2}>
      <HStack spacing={6}>
        <Image systemName="flame.fill" size={22} color={flame} />
        <Text modifiers={[font({ size: 34, weight: 'bold', design: 'rounded' }), foregroundStyle('#FFFFFF'), minimumScaleFactor(0.6), lineLimit(1)]}>{`${p.streak}`}</Text>
      </HStack>
      <Text modifiers={[font({ size: 12, weight: 'semibold' }), foregroundStyle(soft)]}>{p.l.dayStreak}</Text>
    </VStack>
  );

  const weekRow = (
    <HStack spacing={5}>
      {days.map((i) => (
        <VStack key={i} spacing={2}>
          <Text modifiers={[font({ size: 11 }), foregroundStyle(dot(p.week[i] ?? '0', i === 6))]}>●</Text>
          <Text modifiers={[font({ size: 9, weight: 'medium' }), foregroundStyle(i === 6 ? '#FFFFFF' : soft)]}>{p.weekLetters[i] ?? ''}</Text>
        </VStack>
      ))}
    </HStack>
  );

  if (family === 'systemSmall') {
    return (
      <VStack alignment="leading" spacing={6} modifiers={[containerBackground(navy, 'widget'), widgetURL('medalbumin://')]}>
        {streakBlock}
        <Spacer />
        {weekRow}
        <Text modifiers={[font({ size: 11, weight: 'medium' }), foregroundStyle(p.studiedToday ? teal : '#FBBF77'), lineLimit(1), minimumScaleFactor(0.8)]}>
          {p.studiedToday ? `${p.l.studied} · ${goalText}` : p.l.keepIt}
        </Text>
      </VStack>
    );
  }

  // systemMedium
  return (
    <HStack spacing={14} modifiers={[containerBackground(navy, 'widget'), widgetURL('medalbumin://')]}>
      <VStack alignment="leading" spacing={8} modifiers={[frame({ maxWidth: 140, maxHeight: 200, alignment: 'leading' })]}>
        {streakBlock}
        <Spacer />
        {weekRow}
      </VStack>
      <VStack alignment="leading" spacing={10}>
        <VStack alignment="leading" spacing={2}>
          <Text modifiers={[font({ size: 11, weight: 'semibold' }), foregroundStyle(soft)]}>{p.l.today}</Text>
          <Text modifiers={[font({ size: 22, weight: 'bold', design: 'rounded' }), foregroundStyle(p.answered >= p.goal ? teal : '#FFFFFF')]}>{goalText}</Text>
          <Text modifiers={[font({ size: 11 }), foregroundStyle(p.studiedToday ? teal : '#FBBF77'), lineLimit(1), minimumScaleFactor(0.8)]}>{p.studiedToday ? p.l.studied : p.l.keepIt}</Text>
        </VStack>
        <VStack alignment="leading" spacing={2}>
          <Text modifiers={[font({ size: 22, weight: 'bold', design: 'rounded' }), foregroundStyle('#FFFFFF')]}>{p.daysToExam !== null && p.daysToExam >= 0 ? `${p.daysToExam}` : '—'}</Text>
          <Text modifiers={[font({ size: 11 }), foregroundStyle(soft), lineLimit(2), minimumScaleFactor(0.8)]}>{p.daysToExam !== null && p.daysToExam >= 0 ? `${p.l.daysTo} ${p.examLabel}` : p.l.noExam}</Text>
        </VStack>
      </VStack>
      <Spacer />
    </HStack>
  );
};

export default createWidget('StreakWidget', StreakWidget);
