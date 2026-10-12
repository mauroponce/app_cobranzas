import { useCallback, useEffect, useState } from 'react';
import type { NativeStackScreenProps } from '@react-navigation/native-stack';
import { Button, Checkbox, Text, TextInput } from 'react-native-paper';
import { useAuth } from '../../auth/AuthContext';
import { AppHeader } from '../../components/AppHeader';
import { Screen } from '../../components/Screen';
import { SurfaceCard } from '../../components/SurfaceCard';
import { customers, obligations, scopeKey, type LocalCustomer, type LocalObligation } from '../../database/financial';
import { queueOfflineCashPayment } from '../../sync/outbox';
import { syncNow } from '../../sync/worker';
import type { RootStackParamList } from '../../types/navigation';
type Props = NativeStackScreenProps<RootStackParamList, 'RegisterPayment'>;
export function RegisterPaymentScreen({ navigation, route }: Props) {
  const { state } = useAuth(); const [customer, setCustomer] = useState<LocalCustomer>(); const [entries, setEntries] = useState<LocalObligation[]>([]); const [selected, setSelected] = useState<string[]>([]); const [amount, setAmount] = useState(''); const [message, setMessage] = useState('');
  const load = useCallback(() => { if (!state || !route.params?.customerId) return; const scope = scopeKey(state.capabilities.tenant.id, state.capabilities.user.id); customers(scope).then((rows) => setCustomer(rows.find((row) => row.id === route.params?.customerId))); obligations(scope, route.params.customerId).then(setEntries); }, [route.params, state]); useEffect(load, [load]);
  const save = async () => { if (!state || !customer || !/^\d+(\.\d{1,2})?$/.test(amount) || amount === '0') { setMessage('Ingresá un importe válido.'); return; } const operationId = globalThis.crypto.randomUUID(); const recordedAt = new Date().toISOString(); await queueOfflineCashPayment({ operationId, tenantId: state.capabilities.tenant.id, userId: state.capabilities.user.id, deviceId: state.deviceId, recordedAt, payload: { customer_id: customer.id, amount, received_at: recordedAt, allocations: selected.length ? selected.map((account_entry_id) => ({ account_entry_id })) : undefined } }); setMessage('Cobro guardado localmente. Se confirmará al sincronizar.'); syncNow({ token: state.token, deviceId: state.deviceId, tenantId: state.capabilities.tenant.id, userId: state.capabilities.user.id }).catch(() => {}); };
  return <Screen><AppHeader eyebrow="Efectivo" title="Registrar cobro" subtitle={customer?.name ?? 'Cliente no disponible'} onBack={navigation.goBack} /><SurfaceCard><Text>Último saldo confirmado</Text><Text variant="headlineSmall">{entries.length ? `$ ${entries.map((entry) => entry.outstanding_amount).join(' + ')}` : 'Sin obligaciones sincronizadas'}</Text></SurfaceCard><TextInput label="Importe recibido (ARS)" value={amount} onChangeText={(value) => setAmount(value.replace(',', '.').replace(/[^0-9.]/g, ''))} keyboardType="decimal-pad" /><Text variant="titleMedium">Imputación</Text>{entries.map((entry) => <Checkbox.Item key={entry.id} label={`${entry.entry_type}: $ ${entry.outstanding_amount}`} status={selected.includes(entry.id) ? 'checked' : 'unchecked'} onPress={() => setSelected((current) => current.includes(entry.id) ? current.filter((id) => id !== entry.id) : [...current, entry.id])} />)}<Text variant="bodySmall">Sin selección, Laravel aplica la política automática.</Text>{message ? <Text>{message}</Text> : null}<Button mode="contained" onPress={save}>Guardar cobro local</Button></Screen>;
}
