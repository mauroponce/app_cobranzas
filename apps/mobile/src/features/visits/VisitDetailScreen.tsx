import type { NativeStackScreenProps } from '@react-navigation/native-stack';
import { useEffect, useState } from 'react';
import { Button, Text } from 'react-native-paper';
import { useAuth } from '../../auth/AuthContext';
import { AppHeader } from '../../components/AppHeader';
import { EmptyState } from '../../components/EmptyState';
import { Screen } from '../../components/Screen';
import { SurfaceCard } from '../../components/SurfaceCard';
import {
  route as currentRoute,
  routeStops,
  scopeKey,
  type LocalRoute,
  type LocalRouteStop,
} from '../../database/financial';
import type { RootStackParamList } from '../../types/navigation';
import { formatMoney } from '../../data/collectorData';
type Props = NativeStackScreenProps<RootStackParamList, 'VisitDetail'>;
export function VisitDetailScreen({ navigation, route }: Props) {
  const { state } = useAuth();
  const [stop, setStop] = useState<LocalRouteStop>();
  const [activeRoute, setActiveRoute] = useState<LocalRoute | null>(null);
  useEffect(() => {
    if (!state) return;
    const scope = scopeKey(state.capabilities.tenant.id, state.capabilities.user.id);
    routeStops(scope).then((rows) => setStop(rows.find((row) => row.id === route.params.stopId)));
    currentRoute(scope).then(setActiveRoute);
  }, [route.params.stopId, state]);
  if (!stop)
    return (
      <Screen>
        <AppHeader title="Detalle de visita" onBack={navigation.goBack} />
        <EmptyState
          icon="map-marker-off-outline"
          title="Visita no disponible"
          description="La parada todavía no está cargada en este dispositivo."
        />
      </Screen>
    );
  const routeInProgress = activeRoute?.status === 'in_progress';
  const acceptsPayments =
    routeInProgress &&
    (stop.status === 'pending' || (stop.status === 'recorded' && stop.outcome === 'paid'));
  const acceptsOutcome = routeInProgress && stop.status === 'pending';
  return (
    <Screen>
      <AppHeader
        eyebrow={`Visita ${stop.sequence}`}
        title={stop.customer_name}
        subtitle={stop.address || 'Dirección no disponible'}
        onBack={navigation.goBack}
      />
      <SurfaceCard>
        <Text variant="labelMedium">Saldo exigible</Text>
        <Text variant="headlineMedium">{formatMoney(stop.sale_due_amount_cents)}</Text>
        <Text variant="bodySmall">
          Saldo contractual total del cliente: {formatMoney(stop.customer_total_outstanding_cents)}
        </Text>
        <Text>La declaración queda pendiente de conciliación administrativa.</Text>
      </SurfaceCard>
      <Button
        mode="contained"
        disabled={!acceptsOutcome}
        onPress={() => navigation.navigate('VisitOutcome', { stopId: stop.id })}
      >
        Registrar resultado
      </Button>
      <Button
        mode="outlined"
        disabled={!acceptsPayments}
        onPress={() =>
          navigation.navigate('RegisterPayment', {
            visitId: stop.id,
            customerId: stop.customer_id,
          })
        }
      >
        {stop.status === 'recorded' ? 'Registrar otro cobro' : 'Registrar cobro'}
      </Button>
      {!routeInProgress ? (
        <Text>Iniciá la hoja desde la pestaña Ruta antes de registrar un resultado.</Text>
      ) : stop.status === 'recorded' && stop.outcome === 'paid' ? (
        <Text>
          Esta visita ya tiene cobros registrados. Podés agregar otros mientras la hoja siga en
          curso.
        </Text>
      ) : stop.status !== 'pending' ? (
        <Text>La visita está cerrada y no admite nuevos resultados ni cobros.</Text>
      ) : null}
    </Screen>
  );
}
