import { StyleSheet, View } from 'react-native';
import { useNavigation, type NavigationProp } from '@react-navigation/native';
import { Button, useTheme } from 'react-native-paper';
import { AppHeader } from '../components/AppHeader';
import { BalanceCard } from '../components/BalanceCard';
import { EmptyState } from '../components/EmptyState';
import { MetricCard } from '../components/MetricCard';
import { Screen } from '../components/Screen';
import { SectionTitle } from '../components/SectionTitle';
import { formatDate } from '../data/collectorData';
import { spacing } from '../theme/theme';
import type { RootStackParamList } from '../types/navigation';
export function WelcomeScreen() {
  const navigation = useNavigation<NavigationProp<RootStackParamList>>();
  const theme = useTheme();
  return (
    <Screen>
      <AppHeader
        eyebrow={formatDate(new Date())}
        title="Buen día"
        subtitle="Tu jornada de cobranza"
        onNotifications={() => navigation.navigate('Notifications')}
      />
      <BalanceCard label="Recaudación de hoy" amount="—" footer="Sin cobros registrados" />
      <View style={styles.grid}>
        <View style={styles.row}>
          <MetricCard icon="map-marker-path" value="—" label="Visitas del día" />
          <MetricCard icon="check-circle-outline" value="—" label="Completadas" />
        </View>
        <View style={styles.row}>
          <MetricCard icon="cash" value="—" label="Efectivo cobrado" />
          <MetricCard icon="clock-outline" value="—" label="Pendientes" />
        </View>
      </View>
      <SectionTitle
        title="Próximas visitas"
        action="Ver ruta"
        onAction={() => navigation.navigate('MainTabs', { screen: 'Route' })}
      />
      <EmptyState
        icon="map-marker-outline"
        title="Todavía no hay visitas"
        description="Las paradas asignadas aparecerán cuando el servidor habilite la ruta del cobrador."
      />
      <Button
        mode="outlined"
        onPress={() => navigation.navigate('MainTabs', { screen: 'Route' })}
        textColor={theme.colors.primary}
      >
        Abrir mi ruta
      </Button>
    </Screen>
  );
}
const styles = StyleSheet.create({
  grid: { gap: spacing.sm },
  row: { flexDirection: 'row', gap: spacing.sm, alignItems: 'stretch' },
});
