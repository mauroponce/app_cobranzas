import type { NativeStackScreenProps } from '@react-navigation/native-stack';
import { Button, Text } from 'react-native-paper';
import { AppHeader } from '../../components/AppHeader';
import { EmptyState } from '../../components/EmptyState';
import { Screen } from '../../components/Screen';
import { SurfaceCard } from '../../components/SurfaceCard';
import { collectorData, formatMoney } from '../../data/collectorData';
import type { RootStackParamList } from '../../types/navigation';
type Props = NativeStackScreenProps<RootStackParamList, 'VisitDetail'>;
export function VisitDetailScreen({ navigation, route }: Props) {
  const stop = collectorData.routeStops.find((item) => item.id === route.params.stopId);
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
        eyebrow={`Parada ${stop.order}`}
        title={stop.customerName}
        subtitle={stop.address}
        onBack={navigation.goBack}
      />
      <SurfaceCard>
        <Text variant="labelMedium">Saldo exigible</Text>
        <Text variant="headlineMedium">{formatMoney(stop.dueMinor)}</Text>
        <Text>
          {stop.onlinePaymentConfirmed
            ? 'Pago online confirmado: verificá la visita antes de cambiar su estado.'
            : 'Sin pago online confirmado para esta visita.'}
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
        onPress={() => navigation.navigate('RegisterPayment', { customerId: stop.customerId })}
      >
        Registrar cobro
      </Button>
    </Screen>
  );
}
