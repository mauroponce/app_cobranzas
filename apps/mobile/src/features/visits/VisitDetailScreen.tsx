import type { NativeStackScreenProps } from '@react-navigation/native-stack';
import { useEffect, useState } from 'react';
import { Button, Text } from 'react-native-paper';
import { useAuth } from '../../auth/AuthContext';
import { AppHeader } from '../../components/AppHeader';
import { EmptyState } from '../../components/EmptyState';
import { Screen } from '../../components/Screen';
import { SurfaceCard } from '../../components/SurfaceCard';
import { routeStops, scopeKey, type LocalRouteStop } from '../../database/financial';
import type { RootStackParamList } from '../../types/navigation';
type Props = NativeStackScreenProps<RootStackParamList, 'VisitDetail'>;
export function VisitDetailScreen({ navigation, route }: Props) {
  const { state } = useAuth();
  const [stop, setStop] = useState<LocalRouteStop>();
  useEffect(() => {
    if (!state) return;
    routeStops(scopeKey(state.capabilities.tenant.id, state.capabilities.user.id)).then((rows) =>
      setStop(rows.find((row) => row.id === route.params.stopId)),
    );
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
  return (
    <Screen>
      <AppHeader
        eyebrow={`Visita ${stop.sequence}`}
        title={stop.customer_name}
        subtitle={stop.address ?? 'Dirección no disponible'}
        onBack={navigation.goBack}
      />
      <SurfaceCard>
        <Text variant="labelMedium">Saldo exigible</Text>
        <Text variant="headlineMedium">$ {stop.due_amount_cents ?? '0'}</Text>
        <Text>
          La declaración queda pendiente de conciliación administrativa.
        </Text>
      </SurfaceCard>
      <Button
        mode="contained"
        onPress={() => navigation.navigate('VisitOutcome', { stopId: stop.id })}
      >
        Registrar resultado
      </Button>
      <Button
        mode="outlined"
        onPress={() => navigation.navigate('RegisterPayment', { visitId: stop.id })}
      >
        Registrar cobro
      </Button>
    </Screen>
  );
}
