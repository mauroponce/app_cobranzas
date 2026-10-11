import { FlatList, StyleSheet, View } from 'react-native';
import type { NativeStackScreenProps } from '@react-navigation/native-stack';
import { Text } from 'react-native-paper';
import { AppHeader } from '../../components/AppHeader';
import { EmptyState } from '../../components/EmptyState';
import { ListScreen } from '../../components/Screen';
import { SurfaceCard } from '../../components/SurfaceCard';
import { collectorData } from '../../data/collectorData';
import { spacing } from '../../theme/theme';
import type { RootStackParamList } from '../../types/navigation';
type Props = NativeStackScreenProps<RootStackParamList, 'Notifications'>;
export function NotificationsScreen({ navigation }: Props) {
  return (
    <ListScreen>
      <FlatList
        data={collectorData.notices}
        keyExtractor={(item) => item.id}
        contentContainerStyle={styles.list}
        ListHeaderComponent={
          <View style={styles.header}>
            <AppHeader
              eyebrow="Actividad y avisos"
              title="Notificaciones"
              onBack={navigation.goBack}
            />
            <Text variant="bodySmall">
              Pagos online, cambios de ruta, recordatorios y conflictos.
            </Text>
          </View>
        }
        renderItem={({ item }) => (
          <SurfaceCard>
            <Text variant="titleSmall">{item.title}</Text>
            <Text variant="bodyMedium">{item.message}</Text>
            <Text variant="labelSmall">{item.occurredAt}</Text>
          </SurfaceCard>
        )}
        ItemSeparatorComponent={() => <View style={styles.separator} />}
        ListEmptyComponent={
          <EmptyState
            icon="bell-outline"
            title="Sin notificaciones"
            description="Los avisos operativos aparecerán cuando la cuenta esté conectada con el servidor."
          />
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
  header: { gap: spacing.lg, marginBottom: spacing.lg },
  separator: { height: spacing.md },
});
