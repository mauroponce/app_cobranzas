import { useCallback, useEffect, useState } from 'react';
import { useFocusEffect } from '@react-navigation/native';
import type { NativeStackScreenProps } from '@react-navigation/native-stack';
import { Text } from 'react-native-paper';
import { useAuth } from '../../auth/AuthContext';
import { AppHeader } from '../../components/AppHeader';
import { EmptyState } from '../../components/EmptyState';
import { Screen } from '../../components/Screen';
import { SurfaceCard } from '../../components/SurfaceCard';
import {
  customers,
  obligations,
  scopeKey,
  type LocalCustomer,
  type LocalObligation,
} from '../../database/financial';
import type { RootStackParamList } from '../../types/navigation';

type Props = NativeStackScreenProps<RootStackParamList, 'CustomerDetail'>;

export function CustomerDetailScreen({ navigation, route }: Props) {
  const { state } = useAuth();
  const [customer, setCustomer] = useState<LocalCustomer>();
  const [entries, setEntries] = useState<LocalObligation[]>([]);

  const load = useCallback(() => {
    if (!state) return;
    const scope = scopeKey(state.capabilities.tenant.id, state.capabilities.user.id);
    customers(scope).then((rows) =>
      setCustomer(rows.find((row) => row.id === route.params.customerId)),
    );
    obligations(scope, route.params.customerId)
      .then(setEntries)
      .catch(() => setEntries([]));
  }, [route.params.customerId, state]);

  useEffect(load, [load]);
  useFocusEffect(load);

  if (!customer) {
    return (
      <Screen>
        <AppHeader title="Detalle del cliente" onBack={navigation.goBack} />
        <EmptyState
          icon="account-off-outline"
          title="Cliente no disponible"
          description="Sincronizá para recuperar la ficha autorizada de este cliente."
        />
      </Screen>
    );
  }

  return (
    <Screen>
      <AppHeader
        eyebrow="Información sincronizada"
        title={customer.name}
        onBack={navigation.goBack}
      />
      <SurfaceCard>
        <Text variant="labelMedium">Estado</Text>
        <Text variant="titleMedium">
          {customer.status === 'active' ? 'Activo' : customer.status}
        </Text>
      </SurfaceCard>
      <SurfaceCard>
        <Text variant="labelMedium">Obligaciones disponibles</Text>
        <Text variant="titleMedium">
          {entries.length ? `${entries.length} obligaciones` : 'No hay obligaciones sincronizadas'}
        </Text>
      </SurfaceCard>
      {entries.map((entry) => (
        <SurfaceCard key={entry.id}>
          <Text variant="labelMedium">{entry.entry_type}</Text>
          <Text>Vence: {entry.due_date ?? 'Sin fecha informada'}</Text>
          <Text variant="titleMedium">Saldo: $ {entry.outstanding_amount}</Text>
        </SurfaceCard>
      ))}
      <Text variant="bodySmall">
        Los cobros se registran desde una visita asignada y quedan pendientes de conciliación.
      </Text>
    </Screen>
  );
}
