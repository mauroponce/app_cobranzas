import { StyleSheet, View } from 'react-native';
import { Icon, Text, useTheme } from 'react-native-paper';
import { radii, spacing } from '../theme/theme';
import { SurfaceCard } from './SurfaceCard';
export function EmptyState({
  icon,
  title,
  description,
}: {
  icon: string;
  title: string;
  description: string;
}) {
  const theme = useTheme();
  return (
    <SurfaceCard style={styles.card}>
      <View style={[styles.icon, { backgroundColor: theme.colors.primaryContainer }]}>
        <Icon source={icon} size={26} color={theme.colors.primary} />
      </View>
      <Text variant="titleMedium" style={styles.title}>
        {title}
      </Text>
      <Text variant="bodyMedium" style={[styles.copy, { color: theme.colors.onSurfaceVariant }]}>
        {description}
      </Text>
    </SurfaceCard>
  );
}
const styles = StyleSheet.create({
  card: { alignItems: 'center', gap: spacing.sm, paddingVertical: spacing.section },
  icon: {
    width: 50,
    height: 50,
    borderRadius: radii.md,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: spacing.sm,
  },
  title: { fontWeight: '800', textAlign: 'center' },
  copy: { textAlign: 'center', lineHeight: 21 },
});
