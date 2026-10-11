import { StyleSheet, View } from 'react-native';
import { Text, useTheme } from 'react-native-paper';
import { palette, radii, spacing } from '../theme/theme';
export function StatusBadge({
  label,
  kind = 'neutral',
}: {
  label: string;
  kind?: 'success' | 'warning' | 'error' | 'neutral';
}) {
  const theme = useTheme();
  const dark = theme.dark;
  const foreground =
    kind === 'success'
      ? dark
        ? '#83DBC0'
        : palette.success
      : kind === 'warning'
        ? dark
          ? '#FFD08A'
          : palette.warning
        : kind === 'error'
          ? dark
            ? '#FFABB5'
            : palette.error
          : theme.colors.onPrimaryContainer;
  const background =
    kind === 'success'
      ? dark
        ? '#153D35'
        : '#E6F7F1'
      : kind === 'warning'
        ? dark
          ? '#48351A'
          : '#FFF2DF'
        : kind === 'error'
          ? dark
            ? '#4A222A'
            : '#FCEAED'
          : theme.colors.primaryContainer;
  return (
    <View style={[styles.badge, { backgroundColor: background }]}>
      <Text variant="labelSmall" style={{ color: foreground, fontWeight: '800' }}>
        {label}
      </Text>
    </View>
  );
}
const styles = StyleSheet.create({
  badge: {
    alignSelf: 'flex-start',
    borderRadius: radii.sm,
    paddingHorizontal: spacing.sm,
    paddingVertical: spacing.xs,
  },
});
