import { useMemo, useState } from 'react';
import { FlatList, StyleSheet, View } from 'react-native';
import { useNavigation, type NavigationProp } from '@react-navigation/native';
import { Chip, Searchbar, Text } from 'react-native-paper';
import { AppHeader } from '../../components/AppHeader';
import { CustomerCard } from '../../components/CustomerCard';
import { EmptyState } from '../../components/EmptyState';
import { ListScreen } from '../../components/Screen';
import { collectorData } from '../../data/collectorData';
import { spacing } from '../../theme/theme';
import type { RootStackParamList } from '../../types/navigation';
const filters = ['Todos', 'Pendientes', 'Al día'] as const;
export function CustomersScreen() {
  const navigation = useNavigation<NavigationProp<RootStackParamList>>();
  const [query, setQuery] = useState('');
  const [filter, setFilter] = useState<(typeof filters)[number]>('Todos');
  const customers = useMemo(
    () =>
      collectorData.customers.filter(
        (customer) =>
          customer.name.toLocaleLowerCase('es-AR').includes(query.toLocaleLowerCase('es-AR')) &&
          (filter === 'Todos' || customer.status === filter),
      ),
    [query, filter],
  );
  return (
    <ListScreen>
      <FlatList
        data={customers}
        keyExtractor={(item) => item.id}
        keyboardShouldPersistTaps="handled"
        contentContainerStyle={styles.list}
        ListHeaderComponent={
          <View style={styles.header}>
            <AppHeader
              eyebrow="Cartera asignada"
              title="Mis clientes"
              subtitle={
                collectorData.customers.length
                  ? `${collectorData.customers.length} clientes`
                  : 'Sin clientes asignados'
              }
              onNotifications={() => navigation.navigate('Notifications')}
            />
            <Searchbar
              placeholder="Buscar por nombre"
              value={query}
              onChangeText={setQuery}
              accessibilityLabel="Buscar clientes"
            />
            <View style={styles.filters}>
              {filters.map((value) => (
                <Chip key={value} selected={filter === value} onPress={() => setFilter(value)}>
                  {value}
                </Chip>
              ))}
            </View>
            <Text variant="titleMedium" style={styles.title}>
              Clientes
            </Text>
          </View>
        }
        renderItem={({ item }) => (
          <CustomerCard
            customer={item}
            onPress={() => navigation.navigate('CustomerDetail', { customerId: item.id })}
          />
        )}
        ItemSeparatorComponent={() => <View style={styles.separator} />}
        ListEmptyComponent={
          <EmptyState
            icon="account-group-outline"
            title="Sin clientes para mostrar"
            description="La cartera aparecerá cuando el servidor asigne clientes a esta cuenta."
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
  header: { gap: spacing.lg, marginBottom: spacing.md },
  filters: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.sm },
  title: { fontWeight: '800' },
  separator: { height: spacing.md },
});
