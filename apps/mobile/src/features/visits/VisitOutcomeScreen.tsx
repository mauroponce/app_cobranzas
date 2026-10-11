import { useState } from 'react';
import type { NativeStackScreenProps } from '@react-navigation/native-stack';
import { Button, RadioButton, Text, TextInput } from 'react-native-paper';
import { AppHeader } from '../../components/AppHeader';
import { EmptyState } from '../../components/EmptyState';
import { Screen } from '../../components/Screen';
import { SurfaceCard } from '../../components/SurfaceCard';
import { collectorData } from '../../data/collectorData';
import type { RootStackParamList } from '../../types/navigation';
type Props = NativeStackScreenProps<RootStackParamList, 'VisitOutcome'>;
const outcomes = [
  ['full', 'Cobro total'],
  ['partial', 'Cobro parcial'],
  ['absent', 'Cliente ausente'],
  ['unable', 'No pudo pagar'],
  ['promise', 'Promesa de pago'],
  ['online', 'Ya pagó online'],
  ['other', 'Otro motivo'],
] as const;
export function VisitOutcomeScreen({ navigation, route }: Props) {
  const stop = collectorData.routeStops.find((item) => item.id === route.params.stopId);
  const [outcome, setOutcome] = useState('');
  const [notes, setNotes] = useState('');
  if (!stop)
    return (
      <Screen>
        <AppHeader title="Resultado de visita" onBack={navigation.goBack} />
        <EmptyState
          icon="map-marker-off-outline"
          title="Visita no disponible"
          description="No se puede registrar un resultado sin una visita asignada."
        />
      </Screen>
    );
  return (
    <Screen>
      <AppHeader
        eyebrow={stop.customerName}
        title="Resultado de visita"
        subtitle={stop.address}
        onBack={navigation.goBack}
      />
      <SurfaceCard>
        <Text variant="titleMedium">¿Qué ocurrió?</Text>
        <RadioButton.Group onValueChange={setOutcome} value={outcome}>
          {outcomes.map(([value, label]) => (
            <RadioButton.Item key={value} label={label} value={value} />
          ))}
        </RadioButton.Group>
      </SurfaceCard>
      <TextInput
        label="Observaciones"
        value={notes}
        onChangeText={setNotes}
        multiline
        mode="outlined"
      />
      <Text variant="bodySmall">
        La omisión o reprogramación requiere una acción humana autorizada. Los pagos online no
        cancelan visitas automáticamente.
      </Text>
      <Button mode="contained" disabled>
        Guardar resultado · disponible con el servidor
      </Button>
    </Screen>
  );
}
