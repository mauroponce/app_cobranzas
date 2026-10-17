import { useState } from 'react';
import type { NativeStackScreenProps } from '@react-navigation/native-stack';
import { Button, RadioButton, Text, TextInput } from 'react-native-paper';
import { useAuth } from '../../auth/AuthContext';
import { AppHeader } from '../../components/AppHeader';
import { Screen } from '../../components/Screen';
import { payments as localPayments, scopeKey } from '../../database/financial';
import { queueOfflineCollectionDeclaration } from '../../sync/outbox';
import { syncNow } from '../../sync/worker';
import type { RootStackParamList } from '../../types/navigation';

type Props = NativeStackScreenProps<RootStackParamList, 'RegisterPayment'>;

function rejectionReason(result: string | null): string {
  if (!result) return 'El servidor no informó el motivo.';
  try {
    const parsed = JSON.parse(result) as { error?: string };
    return parsed.error ?? 'El servidor no informó el motivo.';
  } catch {
    return 'El servidor no informó el motivo.';
  }
}

export function RegisterPaymentScreen({ navigation, route }: Props) {
  const { state } = useAuth();
  const [method, setMethod] = useState<'cash' | 'bank_transfer' | 'combined'>('cash');
  const [amount, setAmount] = useState('');
  const [transferAmount, setTransferAmount] = useState('');
  const [reference, setReference] = useState('');
  const [message, setMessage] = useState('');
  const [saving, setSaving] = useState(false);

  const save = async () => {
    if (!state) {
      setMessage('La sesión no está disponible. Volvé a ingresar antes de registrar el cobro.');
      return;
    }
    const validAmount = (value: string) =>
      /^\d+(\.\d{1,2})?$/.test(value) && !/^0+(\.0{1,2})?$/.test(value);
    if (!validAmount(amount) || (method === 'combined' && !validAmount(transferAmount))) {
      setMessage('Ingresá un importe mayor que cero, con hasta dos decimales.');
      return;
    }

    const operationId = globalThis.crypto.randomUUID();
    const recordedAt = new Date().toISOString();
    const scope = scopeKey(state.capabilities.tenant.id, state.capabilities.user.id);
    setSaving(true);
    setMessage('');

    try {
      await queueOfflineCollectionDeclaration({
        operationId,
        tenantId: state.capabilities.tenant.id,
        userId: state.capabilities.user.id,
        deviceId: state.deviceId,
        customerId: route.params.customerId,
        recordedAt,
        payload: {
          visit_id: route.params.visitId,
          components:
            method === 'combined'
              ? [
                  { method: 'cash', amount },
                  {
                    method: 'bank_transfer',
                    amount: transferAmount,
                    reference: reference || undefined,
                  },
                ]
              : [{ method, amount, reference: reference || undefined }],
        },
      });
      setAmount('');
      setTransferAmount('');
      setReference('');

      try {
        await syncNow({
          token: state.token,
          deviceId: state.deviceId,
          tenantId: state.capabilities.tenant.id,
          userId: state.capabilities.user.id,
        });
        const saved = (await localPayments(scope)).find(
          (payment) => payment.operation_id === operationId,
        );
        if (saved?.status === 'rejected') {
          setMessage(`El cobro fue rechazado: ${rejectionReason(saved.result)}`);
        } else if (saved?.status === 'pending_review') {
          setMessage(
            'Cobro enviado y pendiente de revisión administrativa. Podés registrar otro cobro en esta visita.',
          );
        } else {
          setMessage(
            'Cobro guardado en este dispositivo y pendiente de sincronización. Podés registrar otro cobro.',
          );
        }
      } catch {
        setMessage(
          'Cobro guardado en este dispositivo. Se enviará cuando vuelva la conexión; no lo cargues nuevamente.',
        );
      }
    } catch {
      setMessage('No se pudo guardar el cobro en este dispositivo. Intentá nuevamente.');
    } finally {
      setSaving(false);
    }
  };

  return (
    <Screen>
      <AppHeader eyebrow="Visita" title="Declarar cobro" onBack={navigation.goBack} />
      <Text>
        Podés registrar varios cobros en esta visita mientras la hoja esté en curso. Cada importe y
        medio de pago se guarda por separado.
      </Text>
      <RadioButton.Group
        value={method}
        onValueChange={(value) => setMethod(value as 'cash' | 'bank_transfer' | 'combined')}
      >
        <RadioButton.Item label="Efectivo" value="cash" />
        <RadioButton.Item label="Transferencia" value="bank_transfer" />
        <RadioButton.Item label="Efectivo y transferencia" value="combined" />
      </RadioButton.Group>
      <TextInput
        label={method === 'combined' ? 'Efectivo recibido (ARS)' : 'Importe recibido (ARS)'}
        value={amount}
        onChangeText={(value) => setAmount(value.replace(',', '.').replace(/[^0-9.]/g, ''))}
        keyboardType="decimal-pad"
      />
      {method === 'combined' ? (
        <TextInput
          label="Transferencia recibida (ARS)"
          value={transferAmount}
          onChangeText={(value) =>
            setTransferAmount(value.replace(',', '.').replace(/[^0-9.]/g, ''))
          }
          keyboardType="decimal-pad"
        />
      ) : null}
      {method !== 'cash' ? (
        <TextInput label="Referencia opcional" value={reference} onChangeText={setReference} />
      ) : null}
      {message ? <Text>{message}</Text> : null}
      <Button mode="contained" loading={saving} disabled={saving} onPress={save}>
        Guardar cobro
      </Button>
    </Screen>
  );
}
