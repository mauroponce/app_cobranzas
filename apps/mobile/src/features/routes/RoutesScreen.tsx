import { useCallback, useEffect, useState } from 'react';
import { FlatList, StyleSheet, View } from 'react-native';
import { useFocusEffect, useNavigation, type NavigationProp } from '@react-navigation/native';
import { Button, Text } from 'react-native-paper';
import { AppHeader } from '../../components/AppHeader';
import { EmptyState } from '../../components/EmptyState';
import { ListScreen } from '../../components/Screen';
import { RouteStopCard } from '../../components/RouteStopCard';
import { StatusBadge } from '../../components/StatusBadge';
import { formatDate } from '../../data/collectorData';
import { useAuth } from '../../auth/AuthContext';
import { route, routeStops, scopeKey, type LocalRoute, type LocalRouteStop } from '../../database/financial';
import { apiPost } from '../../services/api/client';
import { syncNow } from '../../sync/worker';
import { spacing } from '../../theme/theme';
import type { RootStackParamList } from '../../types/navigation';
export function RoutesScreen() {
  const navigation = useNavigation<NavigationProp<RootStackParamList>>();
  const { state } = useAuth();
  const [stops, setStops] = useState<LocalRouteStop[]>([]);
  const [activeRoute, setActiveRoute] = useState<LocalRoute | null>(null);
  const [starting, setStarting] = useState(false);
  const load = useCallback(() => {
    if (!state) return;
    const scope = scopeKey(state.capabilities.tenant.id, state.capabilities.user.id);
    routeStops(scope).then(setStops);
    route(scope).then(setActiveRoute);
  }, [state]);
  useEffect(load, [load]);
  useFocusEffect(load);
  useEffect(() => {
    if (state)
      syncNow({
        token: state.token,
        deviceId: state.deviceId,
        tenantId: state.capabilities.tenant.id,
        userId: state.capabilities.user.id,
      })
        .then(load)
        .catch(() => {});
  }, [state, load]);
  const startRoute = async () => {
    if (!state || !activeRoute || activeRoute.status !== 'published') return;
    setStarting(true);
    try {
      await apiPost(`/collection-routes/${activeRoute.id}/start`, {}, state.token);
      await syncNow({
        token: state.token,
        deviceId: state.deviceId,
        tenantId: state.capabilities.tenant.id,
        userId: state.capabilities.user.id,
      }, { force: true });
      load();
    } finally {
      setStarting(false);
    }
  };
  return (
    <ListScreen>
      <FlatList
        data={stops}
        keyExtractor={(item) => item.id}
        contentContainerStyle={styles.list}
        ListHeaderComponent={
          <View style={styles.header}>
            <AppHeader
              eyebrow="Mi recorrido"
              title="Mi ruta"
              subtitle={formatDate(new Date())}
              onNotifications={() => navigation.navigate('Notifications')}
            />
            <View style={styles.summary}>
              <Text variant="bodySmall">
                Cobrador: {state?.capabilities.user.name ?? 'sin sesión iniciada'}
              </Text>
              <Text variant="bodyMedium">
                Visitas: {stops.length || '—'} · Realizadas:{' '}
                {stops.length ? stops.filter((stop) => stop.status === 'recorded').length : '—'} ·
                Pendientes:{' '}
                {stops.length ? stops.filter((stop) => stop.status === 'pending').length : '—'}
              </Text>
              {activeRoute?.status === 'published' ? (
                <Button mode="contained" loading={starting} onPress={startRoute}>
                  Iniciar hoja
                </Button>
              ) : activeRoute?.status === 'in_progress' ? (
                <StatusBadge label="Hoja en curso" kind="success" />
              ) : (
                <StatusBadge label="Sin hoja disponible" />
              )}
            </View>
            <Text variant="titleMedium" style={styles.title}>
              Paradas de hoy
            </Text>
          </View>
        }
        renderItem={({ item }) => (
          <RouteStopCard
            stop={{
              id: item.id,
              order: item.sequence,
              customerId: item.customer_id,
              customerName: item.customer_name,
              address: item.address ?? 'Dirección sin detalle',
              dueMinor: item.due_amount_cents,
              status: item.status === 'recorded' ? 'completed' : 'pending',
              onlinePaymentConfirmed: false,
            }}
            onPress={() => navigation.navigate('VisitDetail', { stopId: item.id })}
          />
        )}
        ItemSeparatorComponent={() => <View style={styles.separator} />}
        ListEmptyComponent={
          <EmptyState
            icon="map-marker-path"
            title="Sin ruta asignada"
            description="Cuando recibas paradas del servidor, aparecerán ordenadas aquí. Un pago online no cancela una visita automáticamente."
          />
        }
      />
    </ListScreen>
  );
}
const styles = StyleSheet.create({
  list: {
    paddingHorizontal: spacing.xl,
    paddingTop: spacing.lg,
    paddingBottom: spacing.section,
    flexGrow: 1,
  },
  header: { gap: spacing.xl, marginBottom: spacing.md },
  summary: { gap: spacing.sm },
  title: { fontWeight: '800' },
  separator: { height: spacing.md },
});
