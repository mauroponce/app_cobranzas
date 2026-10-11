import { StyleSheet, View } from 'react-native';
import { Icon, Text, useTheme } from 'react-native-paper';
import { spacing } from '../theme/theme';
import { SurfaceCard } from './SurfaceCard';
export function SyncStatusBanner({
  state,
  pending = 0,
}: {
  state: 'connected' | 'offline' | 'checking' | 'error' | 'unknown';
  pending?: number;
}) {
  const theme = useTheme();
  const copy =
    state === 'connected'
      ? 'Conectado con el servidor'
      : state === 'offline'
        ? 'Sin conexión'
        : state === 'checking'
          ? 'Comprobando conexión'
          : state === 'unknown'
            ? 'Conexión sin comprobar'
            : 'Error de conexión';
  return (
    <SurfaceCard>
      <View style={styles.row}>
        <Icon
          source={state === 'connected' ? 'wifi' : 'wifi-off'}
          size={22}
          color={theme.colors.primary}
        />
        <View style={styles.copy}>
          <Text variant="titleSmall" style={styles.bold}>
            {copy}
          </Text>
          <Text variant="bodySmall" style={{ color: theme.colors.onSurfaceVariant }}>
            {pending ? `${pending} operaciones pendientes` : 'Sin operaciones locales registradas'}
          </Text>
        </View>
      </View>
    </SurfaceCard>
  );
}
const styles = StyleSheet.create({
  row: { flexDirection: 'row', gap: spacing.md, alignItems: 'center' },
  copy: { flex: 1, minWidth: 0 },
  bold: { fontWeight: '800' },
});
