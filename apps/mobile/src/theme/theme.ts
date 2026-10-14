import { MD3LightTheme, type MD3Theme } from 'react-native-paper';

// oklch(58.5% 0.233 277.117) converted to sRGB; blue is gamut-clipped.
export const primaryOklch = 'oklch(58.5% 0.233 277.117)';
export const palette = {
  primary: '#615FFF',
  onPrimary: '#FFFFFF',
  primarySoft: '#E9E8FF',
  ink: '#252B42',
  muted: '#737D93',
  background: '#F7F8FC',
  surface: '#FFFFFF',
  border: '#E8EBF3',
  success: '#087F66',
  warning: '#A8620A',
  error: '#BA3545',
} as const;

export const spacing = { xs: 4, sm: 8, md: 12, lg: 16, xl: 20, xxl: 24, section: 28 } as const;
export const radii = { sm: 10, md: 14, card: 18, banner: 24 } as const;
export const shadows = { card: { elevation: 1 } } as const;

const sharedColors = {
  primary: palette.primary,
  onPrimary: palette.onPrimary,
  error: palette.error,
};

export const lightTheme: MD3Theme = {
  ...MD3LightTheme,
  roundness: radii.md,
  colors: {
    ...MD3LightTheme.colors,
    ...sharedColors,
    primaryContainer: palette.primarySoft,
    onPrimaryContainer: '#262281',
    secondary: '#52617D',
    background: palette.background,
    surface: palette.surface,
    surfaceVariant: '#F0F2F8',
    onSurface: palette.ink,
    onSurfaceVariant: palette.muted,
    outline: palette.border,
  },
};
