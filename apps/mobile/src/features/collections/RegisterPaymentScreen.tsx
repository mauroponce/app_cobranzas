import { useState } from 'react';
import type { NativeStackScreenProps } from '@react-navigation/native-stack';
import { Button, RadioButton, Text, TextInput } from 'react-native-paper';
import { useAuth } from '../../auth/AuthContext';
import { AppHeader } from '../../components/AppHeader';
import { Screen } from '../../components/Screen';
import { queueOfflineCollectionDeclaration } from '../../sync/outbox';
import { syncNow } from '../../sync/worker';
import type { RootStackParamList } from '../../types/navigation';

type Props = NativeStackScreenProps<RootStackParamList, 'RegisterPayment'>;

export function RegisterPaymentScreen({ navigation, route }: Props) {
  const { state } = useAuth(); const [method, setMethod] = useState<'cash' | 'bank_transfer'>('cash'); const [amount, setAmount] = useState(''); const [reference, setReference] = useState(''); const [message, setMessage] = useState('');
  const save = async () => {
    if (!state || !/^\d+(\.\d{1,2})?$/.test(amount) || amount === '0') { setMessage('Ingresá un importe válido.'); return; }
    const recordedAt = new Date().toISOString();
    await queueOfflineCollectionDeclaration({ operationId: globalThis.crypto.randomUUID(), tenantId: state.capabilities.tenant.id, userId: state.capabilities.user.id, deviceId: state.deviceId, recordedAt, payload: { visit_id: route.params.visitId, components: [{ method, amount, reference: reference || undefined }] } });
    setMessage('Declaración guardada. Queda pendiente de conciliación administrativa.');
    syncNow({ token: state.token, deviceId: state.deviceId, tenantId: state.capabilities.tenant.id, userId: state.capabilities.user.id }).catch(() => {});
  };
  return <Screen><AppHeader eyebrow="Visita" title="Declarar cobro" onBack={navigation.goBack} /><RadioButton.Group value={method} onValueChange={(value) => setMethod(value as 'cash' | 'bank_transfer')}><RadioButton.Item label="Efectivo" value="cash" /><RadioButton.Item label="Transferencia" value="bank_transfer" /></RadioButton.Group><TextInput label="Importe recibido (ARS)" value={amount} onChangeText={(value) => setAmount(value.replace(',', '.').replace(/[^0-9.]/g, ''))} keyboardType="decimal-pad" />{method === 'bank_transfer' ? <TextInput label="Referencia opcional" value={reference} onChangeText={setReference} /> : null}{message ? <Text>{message}</Text> : null}<Button mode="contained" onPress={save}>Guardar declaración</Button></Screen>;
}
