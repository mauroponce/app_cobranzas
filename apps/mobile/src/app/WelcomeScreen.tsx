import { useCallback, useEffect, useState } from 'react';
import { StyleSheet, View } from 'react-native';
import { useFocusEffect, useNavigation, type NavigationProp } from '@react-navigation/native';
import { Button } from 'react-native-paper';
import { useAuth } from '../auth/AuthContext';
import { AppHeader } from '../components/AppHeader';
import { BalanceCard } from '../components/BalanceCard';
import { EmptyState } from '../components/EmptyState';
import { MetricCard } from '../components/MetricCard';
import { RouteStopCard } from '../components/RouteStopCard';
import { Screen } from '../components/Screen';
import { SectionTitle } from '../components/SectionTitle';
import { formatDate } from '../data/collectorData';
import { pendingCount, routeStops, scopeKey, type LocalRouteStop } from '../database/financial';
import { spacing } from '../theme/theme';
import type { RootStackParamList } from '../types/navigation';

export function WelcomeScreen() {
  const navigation = useNavigation<NavigationProp<RootStackParamList>>();
  const { state } = useAuth();
  const [stops, setStops] = useState<LocalRouteStop[]>([]);
  const [pendingSync, setPendingSync] = useState(0);
  const load = useCallback(() => {
    if (!state) return;
    const scope = scopeKey(state.capabilities.tenant.id, state.capabilities.user.id);
    Promise.all([routeStops(scope), pendingCount(scope)])
      .then(([nextStops, nextPendingSync]) => {
        setStops(nextStops);
        setPendingSync(nextPendingSync);
      })
      .catch(() => {
        setStops([]);
        setPendingSync(0);
      });
  }, [state]);
  useEffect(load, [load]);
  useFocusEffect(load);
  const completed = stops.filter((stop) => stop.status === 'visited').length;
  const pending = stops.filter((stop) => stop.status === 'pending').length;
  const progress = stops.length ? `${completed} de ${stops.length}` : '—';
  return (
    <Screen>
      <AppHeader
        eyebrow={formatDate(new Date())}
        title={`Hola${state?.capabilities.user.name ? `, ${state.capabilities.user.name}` : ''}`}
        subtitle="Tu jornada de cobranza"
        onNotifications={() => navigation.navigate('Notifications')}
      />
      <BalanceCard
        label="Jornada de hoy"
        amount={progress}
        footer={stops.length ? 'Visitas realizadas' : 'Todavía no hay visitas asignadas'}
        icon="map-marker-path"
      />
      <View style={styles.grid}>
        <View style={styles.row}>
          <MetricCard
            icon="map-marker-path"
            value={stops.length ? String(stops.length) : '—'}
            label="Visitas del día"
          />
          <MetricCard
            icon="check-circle-outline"
            value={stops.length ? String(completed) : '—'}
            label="Realizadas"
          />
        </View>
        <View style={styles.row}>
          <MetricCard
            icon="clock-outline"
            value={stops.length ? String(pending) : '—'}
            label="Por visitar"
          />
          <MetricCard
            icon="cloud-upload-outline"
            value={String(pendingSync)}
            label="Sin sincronizar"
          />
        </View>
      </View>
      <SectionTitle
        title="Próximas visitas"
        action="Ver ruta"
        onAction={() => navigation.navigate('MainTabs', { screen: 'Route' })}
      />
      {stops.length ? (
        stops.slice(0, 2).map((stop) => (
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
      <Button mode="outlined" onPress={() => navigation.navigate('MainTabs', { screen: 'Route' })}>
        Abrir mi ruta
      </Button>
    </Screen>
  );
}

const styles = StyleSheet.create({
  grid: { gap: spacing.sm },
  row: { flexDirection: 'row', gap: spacing.sm, alignItems: 'stretch' },
});
