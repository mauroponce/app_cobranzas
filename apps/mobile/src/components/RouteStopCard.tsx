import { Pressable, StyleSheet, View } from 'react-native';
import { Icon, Text, useTheme } from 'react-native-paper';
import type { RouteStop } from '../data/collectorData';
import { formatMoney } from '../data/collectorData';
import { radii, spacing } from '../theme/theme';
import { SurfaceCard } from './SurfaceCard';
import { StatusBadge } from './StatusBadge';
export function RouteStopCard({ stop, onPress }: { stop: RouteStop; onPress: () => void }) {
  const theme = useTheme();
  return (
    <Pressable
      onPress={onPress}
      accessibilityRole="button"
      accessibilityLabel={`Ver visita a ${stop.customerName}`}
    >
      <SurfaceCard>
        <View style={styles.row}>
          <View style={[styles.number, { backgroundColor: theme.colors.primaryContainer }]}>
            <Text style={{ color: theme.colors.primary, fontWeight: '800' }}>{stop.order}</Text>
          </View>
          <View style={styles.copy}>
            <Text variant="titleSmall" numberOfLines={2} style={styles.bold}>
              {stop.customerName}
            </Text>
            <Text
              variant="bodySmall"
              numberOfLines={2}
              style={{ color: theme.colors.onSurfaceVariant }}
            >
              {stop.address}
            </Text>
          </View>
          <Icon source="chevron-right" size={22} color={theme.colors.onSurfaceVariant} />
        </View>
        <View style={styles.foot}>
          <Text variant="bodySmall">Saldo exigible: {formatMoney(stop.dueMinor)}</Text>
          <StatusBadge
            label={stop.status === 'completed' ? 'Realizada' : 'Pendiente'}
            kind={stop.status === 'completed' ? 'success' : 'warning'}
          />
        </View>
        {stop.onlinePaymentConfirmed && (
          <Text variant="labelSmall" style={{ color: theme.colors.primary, marginTop: spacing.sm }}>
            Pago online confirmado · verificar visita
          </Text>
        )}
      </SurfaceCard>
    </Pressable>
  );
}
const styles = StyleSheet.create({
  row: { flexDirection: 'row', alignItems: 'center', gap: spacing.md },
  number: {
    width: 32,
    height: 32,
    borderRadius: radii.sm,
    alignItems: 'center',
    justifyContent: 'center',
  },
  copy: { flex: 1, minWidth: 0, gap: 2 },
  bold: { fontWeight: '800' },
  foot: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    flexWrap: 'wrap',
    gap: spacing.sm,
    marginTop: spacing.md,
  },
});
