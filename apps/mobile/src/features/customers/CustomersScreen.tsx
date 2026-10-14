import { useCallback, useEffect, useState } from 'react';
import { FlatList, Pressable, StyleSheet, View } from 'react-native';
import { useFocusEffect, useNavigation, type NavigationProp } from '@react-navigation/native';
import { Searchbar, Text } from 'react-native-paper';
import { useAuth } from '../../auth/AuthContext';
import { AppHeader } from '../../components/AppHeader';
import { EmptyState } from '../../components/EmptyState';
import { ListScreen } from '../../components/Screen';
import { SurfaceCard } from '../../components/SurfaceCard';
import { customers, scopeKey, type LocalCustomer } from '../../database/financial';
import { spacing } from '../../theme/theme';
import type { RootStackParamList } from '../../types/navigation';

export function CustomersScreen() {
  const navigation = useNavigation<NavigationProp<RootStackParamList>>();
  const { state } = useAuth();
  const [rows, setRows] = useState<LocalCustomer[]>([]);
  const [query, setQuery] = useState('');

  const load = useCallback(() => {
    if (!state) return;
    customers(scopeKey(state.capabilities.tenant.id, state.capabilities.user.id))
      .then(setRows)
      .catch(() => setRows([]));
  }, [state]);

  useEffect(load, [load]);
  useFocusEffect(load);

  const normalizedQuery = query.toLocaleLowerCase('es-AR');
  const data = rows.filter((row) => row.name.toLocaleLowerCase('es-AR').includes(normalizedQuery));

  return (
    <ListScreen>
      <FlatList
        data={data}
        keyExtractor={(row) => row.id}
        contentContainerStyle={styles.list}
        ListHeaderComponent={
          <View style={styles.header}>
            <AppHeader
              eyebrow="Datos disponibles sin conexión"
              title="Clientes"
              subtitle={`${rows.length} clientes sincronizados`}
              onNotifications={() => navigation.navigate('Notifications')}
            />
            <Searchbar
              placeholder="Buscar por nombre"
              value={query}
              onChangeText={setQuery}
              accessibilityLabel="Buscar clientes por nombre"
            />
          </View>
        }
        renderItem={({ item }) => (
          <Pressable
            onPress={() => navigation.navigate('CustomerDetail', { customerId: item.id })}
            accessibilityRole="button"
            accessibilityLabel={`Ver cliente ${item.name}`}
          >
            <SurfaceCard>
              <Text variant="titleMedium">{item.name}</Text>
              <Text variant="bodySmall">{item.status === 'active' ? 'Activo' : item.status}</Text>
            </SurfaceCard>
          </Pressable>
        )}
        ItemSeparatorComponent={() => <View style={styles.separator} />}
        ListEmptyComponent={
          <EmptyState
            icon="account-group-outline"
            title="Sin clientes sincronizados"
            description="Actualizá cuando tengas conexión para consultar la cartera autorizada."
          />
        }
      />
    </ListScreen>
  );
}

const styles = StyleSheet.create({
  list: { padding: spacing.xl, flexGrow: 1 },
  header: { gap: spacing.lg, marginBottom: spacing.md },
  separator: { height: spacing.sm },
});
