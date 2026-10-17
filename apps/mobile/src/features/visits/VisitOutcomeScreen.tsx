import { useEffect, useState } from 'react';
import type { NativeStackScreenProps } from '@react-navigation/native-stack';
import { Button, RadioButton, Text, TextInput } from 'react-native-paper';
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
import { queueOfflineVisitOutcome } from '../../sync/outbox';
import { syncNow } from '../../sync/worker';
import type { RootStackParamList } from '../../types/navigation';

type Props = NativeStackScreenProps<RootStackParamList, 'VisitOutcome'>;
type Reason = 'absent' | 'refused' | 'insufficient_funds' | 'other';
const outcomes: [Reason, string][] = [
  ['absent', 'Cliente ausente'],
  ['refused', 'Rechazó el pago'],
  ['insufficient_funds', 'No pudo pagar'],
  ['other', 'Otro motivo'],
];

export function VisitOutcomeScreen({ navigation, route }: Props) {
  const { state } = useAuth();
  const [stop, setStop] = useState<LocalRouteStop>();
  const [activeRoute, setActiveRoute] = useState<LocalRoute | null>(null);
  const [outcome, setOutcome] = useState<Reason | ''>('');
  const [notes, setNotes] = useState('');
  const [message, setMessage] = useState('');
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    if (!state) return;
    const scope = scopeKey(state.capabilities.tenant.id, state.capabilities.user.id);
    routeStops(scope).then((rows) => setStop(rows.find((row) => row.id === route.params.stopId)));
    currentRoute(scope).then(setActiveRoute);
  }, [route.params.stopId, state]);

  if (!stop) {
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
  }

  const enabled = activeRoute?.status === 'in_progress' && stop.status === 'pending';
  const save = async () => {
    if (!state || !enabled || !outcome) return;
    if (outcome === 'other' && !notes.trim()) {
      setMessage('Detallá el motivo cuando seleccionás Otro.');
      return;
    }
    setSaving(true);
    setMessage('');
    try {
      await queueOfflineVisitOutcome({
        operationId: globalThis.crypto.randomUUID(),
        tenantId: state.capabilities.tenant.id,
        userId: state.capabilities.user.id,
        deviceId: state.deviceId,
        recordedAt: new Date().toISOString(),
        payload: { visit_id: stop.id, reason: outcome, notes: notes.trim() || undefined },
      });
      try {
        await syncNow({
          token: state.token,
          deviceId: state.deviceId,
          tenantId: state.capabilities.tenant.id,
          userId: state.capabilities.user.id,
        });
        setMessage('Resultado sincronizado con el servidor.');
      } catch {
        setMessage('Resultado guardado en este dispositivo y pendiente de sincronización.');
      }
    } catch {
      setMessage('No se pudo guardar el resultado en este dispositivo.');
    } finally {
      setSaving(false);
    }
  };

  return (
    <Screen>
      <AppHeader
        eyebrow={stop.customer_name}
        title="Resultado de visita"
        subtitle={stop.address || 'Dirección no disponible'}
        onBack={navigation.goBack}
      />
      <SurfaceCard>
        <Text variant="titleMedium">¿Por qué no hubo pago?</Text>
        <RadioButton.Group onValueChange={(value) => setOutcome(value as Reason)} value={outcome}>
          {outcomes.map(([value, label]) => (
            <RadioButton.Item key={value} label={label} value={value} disabled={!enabled} />
          ))}
        </RadioButton.Group>
      </SurfaceCard>
      <TextInput
        label={outcome === 'other' ? 'Observaciones obligatorias' : 'Observaciones'}
        value={notes}
        onChangeText={setNotes}
        multiline
        mode="outlined"
        disabled={!enabled}
      />
      {!enabled ? <Text>La visita ya no admite modificaciones.</Text> : null}
      {message ? <Text>{message}</Text> : null}
      <Button
        mode="contained"
        disabled={!enabled || !outcome || saving}
        loading={saving}
        onPress={save}
      >
        Guardar resultado
      </Button>
    </Screen>
  );
}
