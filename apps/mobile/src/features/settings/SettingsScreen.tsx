import { useState } from 'react';
import { StyleSheet, View } from 'react-native';
import { Button, Text } from 'react-native-paper';
import { AppHeader } from '../../components/AppHeader';
import { Screen } from '../../components/Screen';
import { SectionTitle } from '../../components/SectionTitle';
import { SurfaceCard } from '../../components/SurfaceCard';
import { SyncStatusBanner } from '../../components/SyncStatusBanner';
import { apiBaseUrl, getHealth } from '../../services/api/client';
import { useAuth } from '../../auth/AuthContext';
import { spacing } from '../../theme/theme';
export function SettingsScreen() {
  const { state, logout } = useAuth();
  const [connection, setConnection] = useState<'unknown' | 'checking' | 'connected' | 'error'>(
    'unknown',
  );
  const [signingOut, setSigningOut] = useState(false);
  async function checkConnection() {
    setConnection('checking');
    try {
      await getHealth();
      setConnection('connected');
    } catch {
      setConnection('error');
    }
  }
  async function signOut() {
    setSigningOut(true);
    try {
      await logout();
    } finally {
      setSigningOut(false);
    }
  }
  return (
    <Screen>
      <AppHeader eyebrow="Cuenta" title="Mi perfil" subtitle="Configuración del dispositivo" />
      <SurfaceCard>
        <View style={styles.details}>
          <Text variant="titleMedium" style={styles.bold}>
            {state?.capabilities.user.name ?? 'Sin sesión iniciada'}
          </Text>
          <Text variant="bodySmall">
            {state?.capabilities.user.email ??
              'Ingresá con una cuenta de empresa para sincronizar datos.'}
          </Text>
          <Text variant="bodySmall">
            Empresa activa: {state?.capabilities.tenant.name ?? 'Sin seleccionar'}
          </Text>
          {state ? <Text variant="bodySmall">Rol: {state.capabilities.role}</Text> : null}
        </View>
      </SurfaceCard>
      <SectionTitle title="Conectividad y sincronización" />
      <SyncStatusBanner state={connection} />
      <SurfaceCard>
        <View style={styles.details}>
          <Text variant="bodySmall" selectable>
            {apiBaseUrl || 'URL de API sin configurar'}
          </Text>
          <Text variant="bodySmall">Estado de sincronización: Sin operaciones locales</Text>
          <Button mode="outlined" disabled={!apiBaseUrl} onPress={checkConnection}>
            Comprobar conexión
          </Button>
        </View>
      </SurfaceCard>
      <SectionTitle title="Aplicación" />
      <SurfaceCard>
        <Text variant="bodyMedium">App Cobranzas · vista móvil inicial</Text>
        <Text variant="bodySmall">Datos de negocio disponibles al conectar la cuenta.</Text>
      </SurfaceCard>
      <Button
        mode="outlined"
        disabled={!state || signingOut}
        loading={signingOut}
        onPress={signOut}
      >
        {state ? 'Cerrar sesión' : 'Cerrar sesión · sin sesión iniciada'}
      </Button>
    </Screen>
  );
}
const styles = StyleSheet.create({ details: { gap: spacing.sm }, bold: { fontWeight: '800' } });
