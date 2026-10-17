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
import {
  route,
  routeStops,
  scopeKey,
  type LocalRoute,
  type LocalRouteStop,
} from '../../database/financial';
import { apiPost } from '../../services/api/client';
import { syncNow } from '../../sync/worker';
import { palette, spacing } from '../../theme/theme';
import type { RootStackParamList } from '../../types/navigation';
export function RoutesScreen() {
  const navigation = useNavigation<NavigationProp<RootStackParamList>>();
  const { state } = useAuth();
  const [stops, setStops] = useState<LocalRouteStop[]>([]);
  const [activeRoute, setActiveRoute] = useState<LocalRoute | null>(null);
  const [starting, setStarting] = useState(false);
  const [routeMessage, setRouteMessage] = useState('');
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
        .catch((reason: unknown) =>
          setRouteMessage(
            reason instanceof Error
              ? reason.message
              : 'No se pudo actualizar la hoja desde el servidor.',
          ),
        );
  }, [state, load]);
  const startRoute = async () => {
    if (!state || !activeRoute || activeRoute.status !== 'published') return;
    setStarting(true);
    setRouteMessage('');
    let startedOnServer = false;
    try {
      await apiPost(`/collection-routes/${activeRoute.id}/start`, {}, state.token);
      startedOnServer = true;
      await syncNow(
        {
          token: state.token,
          deviceId: state.deviceId,
          tenantId: state.capabilities.tenant.id,
          userId: state.capabilities.user.id,
        },
        { force: true },
      );
      load();
      setRouteMessage('Hoja iniciada. Ya podés registrar resultados y cobros.');
    } catch (reason) {
      const detail =
        reason instanceof Error ? reason.message : 'No se recibió un detalle del servidor.';
      setRouteMessage(
        startedOnServer
          ? `La hoja fue iniciada en el servidor, pero este dispositivo no pudo actualizarse. Volvé a sincronizar antes de registrar cobros. Detalle: ${detail}`
          : `No se pudo iniciar la hoja. No se habilitaron visitas ni cobros. Detalle: ${detail}`,
      );
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
                <>
                  <StatusBadge label="Hoja en curso" kind="success" />
                  <Button mode="outlined" onPress={() => navigation.navigate('Settlement')}>
                    Preparar rendición
                  </Button>
                </>
              ) : activeRoute?.status === 'submitted' ? (
                <StatusBadge label="Rendición presentada" kind="warning" />
              ) : activeRoute?.status === 'completed' ? (
                <StatusBadge label="Hoja cerrada" kind="success" />
              ) : (
                <StatusBadge label="Sin hoja disponible" />
              )}
              {routeMessage ? <Text style={styles.message}>{routeMessage}</Text> : null}
              {!activeRoute ? (
                <Text>
                  Restricción: necesitás una hoja publicada para hoy antes de registrar visitas o
                  cobros.
                </Text>
              ) : activeRoute.status !== 'published' && activeRoute.status !== 'in_progress' ? (
                <Text>
                  Restricción: esta hoja ya no está activa y no admite nuevos resultados ni cobros.
                </Text>
              ) : null}
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
              dueMinor: item.sale_due_amount_cents,
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
  message: { color: palette.ink },
  separator: { height: spacing.md },
});
