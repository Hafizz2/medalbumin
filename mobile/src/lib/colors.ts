import type { Palette } from '../theme/colors';

/**
 * Department colours are set by admins for light mode. In dark mode, use a soft
 * tint of the ink colour instead so tiles don't glow.
 */
export function tileColors(bg: string, ink: string, dark: boolean, c: Palette): { bg: string; ink: string } {
  if (!dark) return { bg, ink };
  const isLightInk = /^#(f|e)/i.test(ink) || ink.toUpperCase() === '#FFFFFF';
  const tone = isLightInk ? c.accent : lighten(ink);
  return { bg: tone + '26', ink: tone };
}

function lighten(hex: string): string {
  const n = parseInt(hex.slice(1), 16);
  const mix = (v: number) => Math.round(v + (255 - v) * 0.45);
  const r = mix((n >> 16) & 255);
  const g = mix((n >> 8) & 255);
  const b = mix(n & 255);
  return '#' + [r, g, b].map((v) => v.toString(16).padStart(2, '0')).join('');
}
