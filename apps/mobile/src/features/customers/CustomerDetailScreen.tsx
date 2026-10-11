import { Linking, StyleSheet, View } from 'react-native';
import type { NativeStackScreenProps } from '@react-navigation/native-stack';
import { Button, Text } from 'react-native-paper';
import { AppHeader } from '../../components/AppHeader';
import { EmptyState } from '../../components/EmptyState';
import { Screen } from '../../components/Screen';
import { SectionTitle } from '../../components/SectionTitle';
import { SurfaceCard } from '../../components/SurfaceCard';
import { collectorData, formatMoney } from '../../data/collectorData';
import { spacing } from '../../theme/theme';
import type { RootStackParamList } from '../../types/navigation';
type Props = NativeStackScreenProps<RootStackParamList, 'CustomerDetail'>;
export function CustomerDetailScreen({ navigation, route }: Props) {
  const customer = collectorData.customers.find((item) => item.id === route.params.customerId);
  if (!customer)
    return (
      <Screen>
        <AppHeader title="Detalle del cliente" onBack={navigation.goBack} />
        <EmptyState
          icon="account-off-outline"
          title="Cliente no disponible"
          description="No hay una ficha de cliente cargada en este dispositivo."
        />
      </Screen>
    );
  return (
    <Screen>
      <AppHeader
        eyebrow="Ficha de cobranza"
        title={customer.name}
        subtitle={customer.address}
        onBack={navigation.goBack}
      />
      <SurfaceCard>
        <Text variant="labelMedium">Saldo exigible</Text>
        <Text variant="headlineMedium" style={styles.bold}>
          {formatMoney(customer.dueMinor)}
        </Text>
      </SurfaceCard>
      <SectionTitle title="Información" />
      <SurfaceCard>
        <View style={styles.details}>
          <Text>Teléfono: {customer.phone ?? 'Sin registrar'}</Text>
          <Text>Dirección: {customer.address}</Text>
          <Text>Cobrador habitual: {customer.collectorName ?? 'Sin asignar'}</Text>
          <Text>Ventas activas: {customer.activeSales ?? '—'}</Text>
          <Text>Cuotas pendientes: {customer.pendingInstallments ?? '—'}</Text>
        </View>
      </SurfaceCard>
      <View style={styles.actions}>
        <Button
          mode="outlined"
          icon="phone"
          disabled={!customer.phone}
          onPress={() => customer.phone && Linking.openURL(`tel:${customer.phone}`)}
        >
          Llamar
        </Button>
        <Button
          mode="outlined"
          icon="map-marker"
          onPress={() =>
            Linking.openURL(
              `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(customer.address)}`,
            )
          }
        >
          Mapa
        </Button>
      </View>
      <Button
        mode="contained"
        icon="cash"
        onPress={() => navigation.navigate('RegisterPayment', { customerId: customer.id })}
      >
        Registrar cobro
      </Button>
      <SectionTitle title="Ventas y pagos" />
      <EmptyState
        icon="receipt-text-outline"
        title="Sin historial disponible"
        description="Cada venta tendrá su propio plan de cuotas cuando el servidor publique estos datos."
      />
    </Screen>
  );
}
const styles = StyleSheet.create({
  bold: { fontWeight: '800' },
  details: { gap: spacing.md },
  actions: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.sm },
});
