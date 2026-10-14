import { useCallback, useEffect, useState } from 'react';
import { StyleSheet, View } from 'react-native';
import { useFocusEffect, useNavigation, type NavigationProp } from '@react-navigation/native';
import { Button, useTheme } from 'react-native-paper';
import { useAuth } from '../auth/AuthContext';
import { AppHeader } from '../components/AppHeader';
import { BalanceCard } from '../components/BalanceCard';
import { EmptyState } from '../components/EmptyState';
import { MetricCard } from '../components/MetricCard';
import { RouteStopCard } from '../components/RouteStopCard';
import { Screen } from '../components/Screen';
import { SectionTitle } from '../components/SectionTitle';
import { formatDate } from '../data/collectorData';
import { routeStops, scopeKey, type LocalRouteStop } from '../database/financial';
import { syncNow } from '../sync/worker';
import { spacing } from '../theme/theme';
import type { RootStackParamList } from '../types/navigation';

export function WelcomeScreen() {
  const navigation = useNavigation<NavigationProp<RootStackParamList>>();
  const { state } = useAuth();
  const theme = useTheme();
  const [stops, setStops] = useState<LocalRouteStop[]>([]);
  const load = useCallback(() => {
    if (state)
      routeStops(scopeKey(state.capabilities.tenant.id, state.capabilities.user.id)).then(setStops);
  }, [state]);
  useEffect(load, [load]);
  useFocusEffect(load);
  useEffect(() => {
    if (!state) return;
    syncNow({
      token: state.token,
      deviceId: state.deviceId,
      tenantId: state.capabilities.tenant.id,
      userId: state.capabilities.user.id,
    })
      .then(load)
      .catch(() => {});
  }, [load, state]);
  const completed = stops.filter((stop) => stop.status === 'visited').length;
  const pending = stops.filter((stop) => stop.status === 'pending').length;
  return (
    <Screen>
      <AppHeader
        eyebrow={formatDate(new Date())}
        title="Buen día"
        subtitle="Tu jornada de cobranza"
        onNotifications={() => navigation.navigate('Notifications')}
      />
      <BalanceCard label="Recaudación de hoy" amount="$ 0,00" footer="Sin cobros registrados hoy" />
      <View style={styles.grid}>
        <View style={styles.row}>
          <MetricCard icon="map-marker-path" value={String(stops.length)} label="Visitas del día" />
          <MetricCard icon="check-circle-outline" value={String(completed)} label="Completadas" />
        </View>
        <View style={styles.row}>
          <MetricCard icon="cash" value="$ 0" label="Efectivo cobrado" />
          <MetricCard icon="clock-outline" value={String(pending)} label="Pendientes" />
        </View>
      </View>
      <SectionTitle
        title="Próximas visitas"
        action="Ver ruta"
        onAction={() => navigation.navigate('MainTabs', { screen: 'Route' })}
      />
      {stops.length ? (
        stops
          .slice(0, 2)
          .map((stop) => (
            <RouteStopCard
              key={stop.id}
              stop={{
                id: stop.id,
                order: stop.sequence,
                customerId: stop.customer_id,
                customerName: stop.customer_name,
                address: stop.address ?? 'Dirección sin detalle',
                dueMinor: stop.due_amount_cents,
                status: stop.status === 'visited' ? 'completed' : 'pending',
                onlinePaymentConfirmed: false,
              }}
              onPress={() => navigation.navigate('VisitDetail', { stopId: stop.id })}
            />
          ))
      ) : (
        <EmptyState
          icon="map-marker-outline"
          title="Todavía no hay visitas"
          description="Sincronizaremos las paradas asignadas cuando tengas conexión."
        />
      )}
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
