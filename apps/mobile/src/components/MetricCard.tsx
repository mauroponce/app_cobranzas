import { StyleSheet, View } from 'react-native';
import { Icon, Text, useTheme } from 'react-native-paper';
import { radii, spacing } from '../theme/theme';
import { SurfaceCard } from './SurfaceCard';
export function MetricCard({ icon, value, label }: { icon: string; value: string; label: string }) {
  const theme = useTheme();
  return (
    <SurfaceCard style={styles.card}>
      <View style={[styles.icon, { backgroundColor: theme.colors.primaryContainer }]}>
        <Icon source={icon} size={21} color={theme.colors.primary} />
      </View>
      <View style={styles.copy}>
        <Text variant="titleLarge" numberOfLines={1} adjustsFontSizeToFit style={styles.value}>
          {value}
        </Text>
        <Text variant="labelMedium" style={{ color: theme.colors.onSurfaceVariant }}>
          {label}
        </Text>
      </View>
    </SurfaceCard>
  );
}
const styles = StyleSheet.create({
  card: {
    flex: 1,
    minWidth: 0,
    minHeight: 142,
    justifyContent: 'space-between',
    padding: spacing.lg,
  },
  icon: {
    width: 38,
    height: 38,
    borderRadius: radii.sm,
    alignItems: 'center',
    justifyContent: 'center',
  },
  copy: { gap: 2 },
  value: { fontWeight: '800' },
});
