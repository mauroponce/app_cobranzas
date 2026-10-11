import { useState } from 'react';
import { StyleSheet, View } from 'react-native';
import { Button, SegmentedButtons, Text } from 'react-native-paper';
import { AppHeader } from '../../components/AppHeader';
import { Screen } from '../../components/Screen';
import { SectionTitle } from '../../components/SectionTitle';
import { SurfaceCard } from '../../components/SurfaceCard';
import { SyncStatusBanner } from '../../components/SyncStatusBanner';
import { apiBaseUrl, getHealth } from '../../services/api/client';
import { useAppearance } from '../../theme/AppearanceContext';
import { spacing } from '../../theme/theme';
export function SettingsScreen() {
  const { preference, setPreference } = useAppearance();
  const [connection, setConnection] = useState<'unknown' | 'checking' | 'connected' | 'error'>(
    'unknown',
  );
  async function checkConnection() {
    setConnection('checking');
    try {
      await getHealth();
      setConnection('connected');
    } catch {
      setConnection('error');
    }
  }
  return (
    <Screen>
      <AppHeader eyebrow="Cuenta" title="Mi perfil" subtitle="Configuración del dispositivo" />
      <SurfaceCard>
        <View style={styles.details}>
          <Text variant="titleMedium" style={styles.bold}>
            Sin sesión de cobrador
          </Text>
          <Text variant="bodySmall">
            La autenticación móvil estará disponible cuando el servidor emita tokens para
            dispositivos.
          </Text>
          <Text variant="bodySmall">Empresa activa: Sin seleccionar</Text>
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
      <SectionTitle title="Apariencia" />
      <SegmentedButtons
        value={preference}
        onValueChange={(value) => setPreference(value as 'system' | 'light' | 'dark')}
        buttons={[
          { value: 'system', label: 'Sistema' },
          { value: 'light', label: 'Claro' },
          { value: 'dark', label: 'Oscuro' },
        ]}
      />
      <SectionTitle title="Aplicación" />
      <SurfaceCard>
        <Text variant="bodyMedium">App Cobranzas · vista móvil inicial</Text>
        <Text variant="bodySmall">Datos de negocio disponibles al conectar la cuenta.</Text>
      </SurfaceCard>
      <Button mode="outlined" disabled>
        Cerrar sesión · sin sesión iniciada
      </Button>
    </Screen>
  );
}
const styles = StyleSheet.create({ details: { gap: spacing.sm }, bold: { fontWeight: '800' } });
