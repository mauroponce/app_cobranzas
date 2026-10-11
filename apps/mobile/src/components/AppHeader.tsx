import { View, StyleSheet } from 'react-native';
import { IconButton, Text, useTheme } from 'react-native-paper';
import { spacing } from '../theme/theme';

type Props = {
  eyebrow?: string;
  title: string;
  subtitle?: string;
  onBack?: () => void;
  onNotifications?: () => void;
};
export function AppHeader({ eyebrow, title, subtitle, onBack, onNotifications }: Props) {
  const theme = useTheme();
  return (
    <View style={styles.row}>
      {onBack && (
        <IconButton
          icon="arrow-left"
          onPress={onBack}
          accessibilityLabel="Volver"
          style={styles.back}
        />
      )}
      <View style={styles.copy}>
        {eyebrow && (
          <Text
            variant="labelSmall"
            style={{ color: theme.colors.primary, letterSpacing: 1.2, fontWeight: '800' }}
          >
            {eyebrow.toUpperCase()}
          </Text>
        )}
        <Text variant="headlineSmall" style={[styles.title, { color: theme.colors.onSurface }]}>
          {title}
        </Text>
        {subtitle && (
          <Text variant="bodySmall" style={{ color: theme.colors.onSurfaceVariant }}>
            {subtitle}
          </Text>
        )}
      </View>
      {onNotifications && (
        <IconButton
          icon="bell-outline"
          mode="outlined"
          onPress={onNotifications}
          accessibilityLabel="Abrir notificaciones"
          style={styles.bell}
        />
      )}
    </View>
  );
}
const styles = StyleSheet.create({
  row: { flexDirection: 'row', alignItems: 'center', gap: spacing.sm, minWidth: 0 },
  copy: { flex: 1, gap: 3, minWidth: 0 },
  title: { fontWeight: '800', letterSpacing: -0.7, lineHeight: 31 },
  bell: { margin: 0 },
  back: { marginLeft: -8 },
});
