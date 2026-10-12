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
  const { state } = useAuth(); const [rows, setRows] = useState<LocalCustomer[]>([]); const [query, setQuery] = useState('');
  const load = useCallback(() => { if (state) customers(scopeKey(state.capabilities.tenant.id, state.capabilities.user.id)).then(setRows).catch(() => setRows([])); }, [state]);
  useEffect(load, [load]); useFocusEffect(load);
  const data = rows.filter((row) => row.name.toLocaleLowerCase('es-AR').includes(query.toLocaleLowerCase('es-AR')));
  const canViewAssignments = Boolean(state?.capabilities.permissions['customers.view_assignments']);
  return <ListScreen><FlatList data={data} keyExtractor={(row) => row.id} contentContainerStyle={styles.list} ListHeaderComponent={<View style={styles.header}><AppHeader eyebrow="Datos disponibles sin conexión" title="Clientes" subtitle={`${rows.length} clientes sincronizados`} onNotifications={() => navigation.navigate('Notifications')} /><Searchbar placeholder="Buscar por nombre" value={query} onChangeText={setQuery} /></View>} renderItem={({ item }) => <Pressable onPress={() => navigation.navigate('CustomerDetail', { customerId: item.id })}><SurfaceCard><Text variant="titleMedium">{item.name}</Text><Text variant="bodySmall">{item.status === 'active' ? 'Activo' : item.status}</Text>{canViewAssignments && item.assignment_status ? <Text variant="bodySmall">{item.collector_name ? `Cobrador: ${item.collector_name}` : 'Sin cobrador'}{item.zone_name ? ` · ${item.zone_name}` : ''}</Text> : null}</SurfaceCard></Pressable>} ItemSeparatorComponent={() => <View style={{ height: spacing.sm }} />} ListEmptyComponent={<EmptyState icon="account-group-outline" title="Sin clientes sincronizados" description="Actualizá cuando tengas conexión." />} /></ListScreen>;
}
const styles = StyleSheet.create({ list: { padding: spacing.xl, flexGrow: 1 }, header: { gap: spacing.lg, marginBottom: spacing.md } });
