import type { NativeStackScreenProps } from '@react-navigation/native-stack';
import { Button, Text } from 'react-native-paper';
import { AppHeader } from '../../components/AppHeader';
import { BalanceCard } from '../../components/BalanceCard';
import { EmptyState } from '../../components/EmptyState';
import { Screen } from '../../components/Screen';
import { SectionTitle } from '../../components/SectionTitle';
import { SurfaceCard } from '../../components/SurfaceCard';
import type { RootStackParamList } from '../../types/navigation';
type Props = NativeStackScreenProps<RootStackParamList, 'Settlement'>;
export function SettlementScreen({ navigation }: Props) {
  return (
    <Screen>
      <AppHeader eyebrow="Efectivo del cobrador" title="Rendición" onBack={navigation.goBack} />
      <BalanceCard
        label="Efectivo pendiente de rendir"
        amount="—"
        footer="Sin cobros en efectivo registrados"
      />
      <SurfaceCard>
        <Text variant="titleMedium">Resumen</Text>
        <Text>Efectivo registrado: —</Text>
        <Text>Cobros incluidos: —</Text>
        <Text>Estado de revisión: Sin rendiciones</Text>
      </SurfaceCard>
      <Text variant="bodySmall">
        Solo se incluirá dinero físico recibido por el cobrador. Los pagos online de Mercado Pago se
        concilian por separado.
      </Text>
      <SectionTitle title="Rendiciones anteriores" />
      <EmptyState
        icon="receipt-text-outline"
        title="Sin rendiciones"
        description="Las rendiciones enviadas y su revisión administrativa aparecerán aquí."
      />
      <Button mode="contained" disabled>
        Preparar rendición · disponible con el servidor
      </Button>
    </Screen>
  );
}
