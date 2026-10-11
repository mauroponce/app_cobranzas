import { StyleSheet, View } from 'react-native';
import { Icon, Text } from 'react-native-paper';
import { palette, radii, spacing } from '../theme/theme';
export function BalanceCard({
  label,
  amount,
  footer,
  icon = 'wallet-outline',
}: {
  label: string;
  amount: string;
  footer: string;
  icon?: string;
}) {
  return (
    <View style={styles.card}>
      <View style={styles.head}>
        <Text variant="labelMedium" style={styles.label}>
          {label.toUpperCase()}
        </Text>
        <Icon source={icon} size={22} color="#FFFFFF" />
      </View>
      <Text variant="displaySmall" adjustsFontSizeToFit numberOfLines={1} style={styles.amount}>
        {amount}
      </Text>
      <View style={styles.rule} />
      <Text variant="bodySmall" style={styles.footer}>
        {footer}
      </Text>
    </View>
  );
}
const styles = StyleSheet.create({
  card: {
    backgroundColor: palette.primary,
    borderRadius: radii.banner,
    padding: spacing.xxl,
    minHeight: 172,
    justifyContent: 'space-between',
    gap: spacing.md,
    elevation: 3,
  },
  head: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    gap: spacing.sm,
  },
  label: { color: '#E9E8FF', fontWeight: '800', letterSpacing: 1.1, flex: 1 },
  amount: { color: '#FFFFFF', fontWeight: '800', letterSpacing: -1.1, minHeight: 46 },
  rule: { height: 1, backgroundColor: '#FFFFFF55' },
  footer: { color: '#F1F0FF' },
});
