import { Pressable, StyleSheet, View } from 'react-native';
import { Text, Icon, useTheme } from 'react-native-paper';
import type { Customer } from '../data/collectorData';
import { formatMoney } from '../data/collectorData';
import { radii, spacing } from '../theme/theme';
import { SurfaceCard } from './SurfaceCard';
import { StatusBadge } from './StatusBadge';
export function CustomerCard({ customer, onPress }: { customer: Customer; onPress: () => void }) {
  const theme = useTheme();
  return (
    <Pressable
      onPress={onPress}
      accessibilityRole="button"
      accessibilityLabel={`Ver cliente ${customer.name}`}
    >
      <SurfaceCard>
        <View style={styles.row}>
          <View style={[styles.avatar, { backgroundColor: theme.colors.primaryContainer }]}>
            <Text style={{ color: theme.colors.primary, fontWeight: '800' }}>
              {customer.name.slice(0, 2).toUpperCase()}
            </Text>
          </View>
          <View style={styles.copy}>
            <Text variant="titleSmall" numberOfLines={2} style={styles.bold}>
              {customer.name}
            </Text>
            <Text
              variant="bodySmall"
              numberOfLines={2}
              style={{ color: theme.colors.onSurfaceVariant }}
            >
              {customer.address}
            </Text>
          </View>
          <Icon source="chevron-right" size={22} color={theme.colors.onSurfaceVariant} />
        </View>
        <View style={styles.foot}>
          <StatusBadge label={customer.status} />
          <Text variant="titleSmall" style={styles.bold}>
            {formatMoney(customer.dueMinor)}
          </Text>
        </View>
      </SurfaceCard>
    </Pressable>
  );
}
const styles = StyleSheet.create({
  row: { flexDirection: 'row', alignItems: 'center', gap: spacing.md },
  avatar: {
    width: 44,
    height: 44,
    borderRadius: radii.md,
    alignItems: 'center',
    justifyContent: 'center',
  },
  copy: { flex: 1, minWidth: 0, gap: 2 },
  bold: { fontWeight: '800' },
  foot: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginTop: spacing.md,
    gap: spacing.sm,
  },
});
