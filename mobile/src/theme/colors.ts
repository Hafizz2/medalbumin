/**
 * MedAlbumin colour tokens, taken from the design files (MedAlbumin-App-Design/screens).
 * Dark mode follows the dark reference concept: deep navy surfaces with teal accents.
 */
export const light = {
  page: '#F3F6FA',
  card: '#FFFFFF',
  line: '#E0E6EE',
  divider: '#EBEFF5',
  field: '#D8DFEA',
  fieldStrong: '#C3CCDA',
  chip: '#E6EBF2',
  chip2: '#EEF2F7',

  text: '#062A5E',
  textSoft: '#475569',
  muted: '#56657D',
  placeholder: '#677489',

  navy: '#062A5E', // hero cards, tab bar, primary buttons
  navy2: '#12407F',
  navy3: '#1B4A8A',
  onNavy: '#F3F6FA',
  onNavySoft: '#A9BBD8',
  onNavyMuted: '#94A6C4',
  onNavyLabel: '#C5D3EA',

  teal: '#017F8E',
  tealDark: '#01606B',
  tealInk: '#015A66',
  accent: '#14BCC4',
  tealSoft: '#DDF3F5',
  tealWash: '#EDF7F9',

  warnBg: '#FFF3E8',
  warn: '#8A3412',
  dangerBg: '#FFE8D6',
  danger: '#B4441A',
  danger2: '#D9692F',
  stopBg: '#F3E3E0',
  stop: '#8A2A1A',
  plumBg: '#F0E9FB',
  plum: '#6B3FB8',
  plumInk: '#5A3399',
  skyBg: '#E4ECFB',
  sky: '#2550A8',
  sandBg: '#FFF3D6',
  sand: '#7A5A12',
  amber: '#FBBF77',
  gold: '#D4A72C',
  dot: '#E2622F',

  overlay: 'rgba(6,42,94,0.45)',
  shadow: 'rgba(6,42,94,0.12)',
};

export type Palette = typeof light;

export const dark: Palette = {
  page: '#071628',
  card: '#0D2340',
  line: '#1A355A',
  divider: '#16304F',
  field: '#24456F',
  fieldStrong: '#33557F',
  chip: '#16304F',
  chip2: '#132B49',

  text: '#EAF1F8',
  textSoft: '#B9C7DA',
  muted: '#8FA3BE',
  placeholder: '#7088A6',

  navy: '#0F3263',
  navy2: '#1A4683',
  navy3: '#24508F',
  onNavy: '#F3F6FA',
  onNavySoft: '#A9BBD8',
  onNavyMuted: '#94A6C4',
  onNavyLabel: '#C5D3EA',

  teal: '#22C3CB',
  tealDark: '#14BCC4',
  tealInk: '#7FE1E6',
  accent: '#14BCC4',
  tealSoft: '#0F3A47',
  tealWash: '#0E3040',

  warnBg: '#3A2614',
  warn: '#FFB27A',
  dangerBg: '#3D2015',
  danger: '#FF9A6B',
  danger2: '#F28C5B',
  stopBg: '#3D1E1A',
  stop: '#FF9C8C',
  plumBg: '#2A2150',
  plum: '#B49AF0',
  plumInk: '#C7B4F5',
  skyBg: '#16295A',
  sky: '#8FB2F5',
  sandBg: '#3A3015',
  sand: '#F2CF77',
  amber: '#FBBF77',
  gold: '#E5BE4A',
  dot: '#FF7A45',

  overlay: 'rgba(0,0,0,0.6)',
  shadow: 'rgba(0,0,0,0.4)',
};

/** Department / category tints (icon tile background + ink) that read well in both modes. */
export const tints: Record<string, { light: [string, string]; dark: [string, string] }> = {
  teal: { light: ['#DDF3F5', '#017F8E'], dark: ['#0F3A47', '#5FDCE2'] },
  sky: { light: ['#E4ECFB', '#2550A8'], dark: ['#16295A', '#8FB2F5'] },
  plum: { light: ['#F0E9FB', '#6B3FB8'], dark: ['#2A2150', '#B49AF0'] },
  sand: { light: ['#FFF3D6', '#7A5A12'], dark: ['#3A3015', '#F2CF77'] },
  danger: { light: ['#FFE8D6', '#B4441A'], dark: ['#3D2015', '#FF9A6B'] },
  slate: { light: ['#E6EBF2', '#062A5E'], dark: ['#16304F', '#C5D3EA'] },
};
