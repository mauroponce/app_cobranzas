import { StyleSheet, View } from 'react-native';
import { Icon, Text, useTheme } from 'react-native-paper';
import type { Payment } from '../data/collectorData';
import { formatMoney } from '../data/collectorData';
import { radii, spacing } from '../theme/theme';
import { SurfaceCard } from './SurfaceCard';
import { StatusBadge } from './StatusBadge';
export function PaymentCard({ payment }: { payment: Payment }) {
  const theme = useTheme();
  return (
    <SurfaceCard>
      <View style={styles.row}>
        <View style={[styles.icon, { backgroundColor: theme.colors.primaryContainer }]}>
          <Icon
            source={payment.method === 'online' ? 'credit-card-outline' : 'cash'}
            size={22}
            color={theme.colors.primary}
          />
        </View>
        <View style={styles.copy}>
          <Text variant="titleSmall" numberOfLines={2} style={styles.bold}>
            {payment.customerName}
          </Text>
          <Text variant="bodySmall" style={{ color: theme.colors.onSurfaceVariant }}>
            {payment.method === 'cash'
              ? 'Efectivo'
              : payment.method === 'transfer'
                ? 'Transferencia'
                : 'Pago online'}{' '}
            · {payment.occurredAt}
          </Text>
        </View>
        <View style={styles.amount}>
          <Text variant="titleSmall" numberOfLines={1} adjustsFontSizeToFit style={styles.bold}>
            {formatMoney(payment.amountMinor)}
          </Text>
          <StatusBadge
            label={
              payment.syncState === 'confirmed'
                ? 'Confirmado'
                : payment.syncState === 'pending'
                  ? 'Pendiente'
                  : 'Revisar'
            }
            kind={
              payment.syncState === 'confirmed'
                ? 'success'
                : payment.syncState === 'pending'
                  ? 'warning'
                  : 'error'
            }
          />
        </View>
      </View>
    </SurfaceCard>
  );
}
const styles = StyleSheet.create({
  row: { flexDirection: 'row', alignItems: 'center', gap: spacing.sm },
  icon: {
    width: 42,
    height: 42,
    borderRadius: radii.md,
    alignItems: 'center',
    justifyContent: 'center',
  },
  copy: { flex: 1, minWidth: 0 },
  bold: { fontWeight: '800' },
  amount: { alignItems: 'flex-end', maxWidth: '35%', gap: spacing.xs },
});
