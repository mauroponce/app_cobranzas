import { useCallback, useEffect, useState } from 'react';
import { useFocusEffect } from '@react-navigation/native';
import type { NativeStackScreenProps } from '@react-navigation/native-stack';
import { Button, Text } from 'react-native-paper';
import { useAuth } from '../../auth/AuthContext';
import { AppHeader } from '../../components/AppHeader';
import { EmptyState } from '../../components/EmptyState';
import { Screen } from '../../components/Screen';
import { SurfaceCard } from '../../components/SurfaceCard';
import { customers, obligations, scopeKey, type LocalCustomer, type LocalObligation } from '../../database/financial';
import type { RootStackParamList } from '../../types/navigation';
type Props = NativeStackScreenProps<RootStackParamList, 'CustomerDetail'>;
export function CustomerDetailScreen({ navigation, route }: Props) {
  const { state } = useAuth(); const [customer, setCustomer] = useState<LocalCustomer>(); const [entries, setEntries] = useState<LocalObligation[]>([]);
  const load = useCallback(() => { if (!state) return; const scope = scopeKey(state.capabilities.tenant.id, state.capabilities.user.id); customers(scope).then((rows) => setCustomer(rows.find((row) => row.id === route.params.customerId))); obligations(scope, route.params.customerId).then(setEntries); }, [route.params.customerId, state]); useEffect(load, [load]); useFocusEffect(load);
  if (!customer) return <Screen><AppHeader title="Detalle" onBack={navigation.goBack} /><EmptyState icon="account-off-outline" title="Cliente no disponible" description="Sincronizá para recuperar esta ficha." /></Screen>;
  const canViewAssignments = Boolean(state?.capabilities.permissions['customers.view_assignments']);
  return <Screen><AppHeader eyebrow="Saldo confirmado" title={customer.name} onBack={navigation.goBack} />{canViewAssignments && customer.assignment_status ? <SurfaceCard><Text variant="labelMedium">Cartera de cobranza</Text><Text>{customer.collector_name ? `Cobrador habitual: ${customer.collector_name}` : 'Sin cobrador habitual'}</Text>{customer.zone_name ? <Text>Zona: {customer.zone_name}</Text> : null}</SurfaceCard> : null}<SurfaceCard><Text variant="labelMedium">Obligaciones pendientes</Text><Text variant="headlineSmall">{entries.length ? `$ ${entries.map((entry) => entry.outstanding_amount).join(' + ')}` : '—'}</Text></SurfaceCard>{entries.map((entry) => <SurfaceCard key={entry.id}><Text>{entry.entry_type} · vence {entry.due_date ?? 'sin fecha'}</Text><Text variant="titleMedium">$ {entry.outstanding_amount}</Text></SurfaceCard>)}{state?.capabilities.permissions['payments.create'] ? <Button mode="contained" icon="cash" onPress={() => navigation.navigate('RegisterPayment', { customerId: customer.id })}>Registrar cobro</Button> : null}</Screen>;
}
