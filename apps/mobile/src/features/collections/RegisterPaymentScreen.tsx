import { useState } from 'react';
import { StyleSheet, View } from 'react-native';
import type { NativeStackScreenProps } from '@react-navigation/native-stack';
import { Button, SegmentedButtons, Text, TextInput } from 'react-native-paper';
import { AppHeader } from '../../components/AppHeader';
import { Screen } from '../../components/Screen';
import { SurfaceCard } from '../../components/SurfaceCard';
import { collectorData, formatMoney } from '../../data/collectorData';
import { spacing } from '../../theme/theme';
import type { RootStackParamList } from '../../types/navigation';
type Props = NativeStackScreenProps<RootStackParamList, 'RegisterPayment'>;
export function RegisterPaymentScreen({ navigation, route }: Props) {
  const customer = collectorData.customers.find((item) => item.id === route.params?.customerId);
  const [amount, setAmount] = useState('');
  const [method, setMethod] = useState('cash');
  return (
    <Screen>
      <AppHeader
        eyebrow="Cobranza presencial"
        title="Registrar cobro"
        subtitle={customer?.name ?? 'Sin cliente seleccionado'}
        onBack={navigation.goBack}
      />
      <SurfaceCard>
        <Text variant="labelMedium">Último saldo disponible</Text>
        <Text variant="headlineMedium" style={styles.bold}>
          {formatMoney(customer?.dueMinor ?? null)}
        </Text>
        <Text variant="bodySmall">
          El saldo definitivo debe confirmarlo el servidor antes de imputar cuotas.
        </Text>
      </SurfaceCard>
      <TextInput
        label="Importe recibido (ARS)"
        value={amount}
        onChangeText={(value) => setAmount(value.replace(/[^0-9,]/g, ''))}
        keyboardType="decimal-pad"
        mode="outlined"
        accessibilityLabel="Importe recibido"
      />
      <Text variant="titleMedium" style={styles.bold}>
        Medio de pago
      </Text>
      <SegmentedButtons
        value={method}
        onValueChange={setMethod}
        buttons={[
          { value: 'cash', label: 'Efectivo', icon: 'cash' },
          { value: 'transfer', label: 'Transferencia', icon: 'bank-outline' },
        ]}
      />
      <SurfaceCard>
        <View style={styles.note}>
          <Text variant="bodyMedium" style={styles.bold}>
            Cuotas a imputar
          </Text>
          <Text variant="bodySmall">
            La selección de cuotas estará disponible cuando el servidor entregue el plan vigente de
            cada venta.
          </Text>
        </View>
      </SurfaceCard>
      <SurfaceCard>
        <Text variant="bodySmall">
          {method === 'cash'
            ? 'Un cobro sin conexión debe figurar como “Pendiente de sincronización” hasta recibir respuesta del servidor.'
            : 'Una transferencia requiere verificación. No se marcará como acreditada al cargarla.'}
        </Text>
      </SurfaceCard>
      <Button mode="contained" disabled>
        Confirmar cobro · disponible con el servidor
      </Button>
    </Screen>
  );
}
const styles = StyleSheet.create({ bold: { fontWeight: '800' }, note: { gap: spacing.sm } });
