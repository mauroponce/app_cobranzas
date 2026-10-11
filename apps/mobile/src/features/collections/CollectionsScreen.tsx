import { FlatList, StyleSheet, View } from 'react-native';
import { useNavigation, type NavigationProp } from '@react-navigation/native';
import { Button } from 'react-native-paper';
import { AppHeader } from '../../components/AppHeader';
import { BalanceCard } from '../../components/BalanceCard';
import { EmptyState } from '../../components/EmptyState';
import { MetricCard } from '../../components/MetricCard';
import { PaymentCard } from '../../components/PaymentCard';
import { ListScreen } from '../../components/Screen';
import { SectionTitle } from '../../components/SectionTitle';
import { SyncStatusBanner } from '../../components/SyncStatusBanner';
import { collectorData } from '../../data/collectorData';
import { spacing } from '../../theme/theme';
import type { RootStackParamList } from '../../types/navigation';
export function CollectionsScreen() {
  const navigation = useNavigation<NavigationProp<RootStackParamList>>();
  return (
    <ListScreen>
      <FlatList
        data={collectorData.payments}
        keyExtractor={(item) => item.id}
        contentContainerStyle={styles.list}
        ListHeaderComponent={
          <View style={styles.header}>
            <AppHeader
              eyebrow="Movimientos"
              title="Mis cobranzas"
              subtitle="Historial y efectivo pendiente"
              onNotifications={() => navigation.navigate('Notifications')}
            />
            <BalanceCard
              label="Total cobrado"
              amount="—"
              footer="Sin operaciones registradas"
              icon="cash-multiple"
            />
            <View style={styles.row}>
              <MetricCard icon="check-circle-outline" value="—" label="Confirmadas" />
              <MetricCard icon="cloud-upload-outline" value="—" label="Locales pendientes" />
            </View>
            <View style={styles.row}>
              <MetricCard icon="alert-circle-outline" value="—" label="Conflictos" />
              <MetricCard icon="receipt-text-outline" value="—" label="Operaciones" />
            </View>
            <SyncStatusBanner state="unknown" />
            <SectionTitle title="Movimientos recientes" />
          </View>
        }
        renderItem={({ item }) => <PaymentCard payment={item} />}
        ItemSeparatorComponent={() => <View style={styles.separator} />}
        ListEmptyComponent={
          <EmptyState
            icon="wallet-outline"
            title="Sin movimientos"
            description="Los cobros confirmados, locales pendientes y conflictos aparecerán aquí cuando se habilite el registro de operaciones."
          />
        }
        ListFooterComponent={
          <Button
            mode="outlined"
            style={styles.footer}
            onPress={() => navigation.navigate('Settlement')}
          >
            Ver rendición de efectivo
          </Button>
        }
      />
    </ListScreen>
  );
}
const styles = StyleSheet.create({
  list: {
    paddingHorizontal: spacing.xl,
    paddingTop: spacing.lg,
    paddingBottom: spacing.section,
    flexGrow: 1,
  },
  header: { gap: spacing.lg, marginBottom: spacing.md },
  row: { flexDirection: 'row', gap: spacing.sm },
  separator: { height: spacing.md },
  footer: { marginTop: spacing.lg },
});
