import { useCallback, useEffect, useState } from 'react';
import { FlatList, StyleSheet, View } from 'react-native';
import { useFocusEffect } from '@react-navigation/native';
import { Button, Text } from 'react-native-paper';
import { useAuth } from '../../auth/AuthContext';
import { AppHeader } from '../../components/AppHeader';
import { EmptyState } from '../../components/EmptyState';
import { ListScreen } from '../../components/Screen';
import { SectionTitle } from '../../components/SectionTitle';
import { SurfaceCard } from '../../components/SurfaceCard';
import {
  payments,
  pendingCount,
  scopeKey,
  syncInfo,
  type LocalPayment,
} from '../../database/financial';
import { syncNow } from '../../sync/worker';
import { spacing } from '../../theme/theme';

function resultError(result: string | null): string | null {
  if (!result) return null;
  try {
    return (JSON.parse(result) as { error?: string }).error ?? null;
  } catch {
    return null;
  }
}

function statusMessage(payment: LocalPayment): string {
  if (payment.status === 'confirmed') return 'Confirmado por el servidor.';
  if (payment.status === 'pending_review') {
    return 'Enviado. Pendiente de revisión administrativa.';
  }
  if (payment.status === 'under_review') return 'En revisión administrativa.';
  if (payment.status === 'rejected') {
    return `Rechazado: ${resultError(payment.result) ?? 'el servidor no informó el motivo.'}`;
  }
  if (payment.status === 'blocked_auth') {
    return 'Sincronización bloqueada: volvé a iniciar sesión.';
  }
  if (payment.status === 'blocked_authorization') {
    return 'Sincronización bloqueada: ya no tenés permiso para registrar este cobro.';
  }
  return 'Guardado en este dispositivo. Pendiente de sincronización.';
}

export function CollectionsScreen() {
  const { state } = useAuth();
  const [rows, setRows] = useState<LocalPayment[]>([]);
  const [pending, setPending] = useState(0);
  const [syncing, setSyncing] = useState(false);
  const [lastSuccess, setLastSuccess] = useState<string | null>(null);
  const [lastError, setLastError] = useState<string | null>(null);

  const load = useCallback(() => {
    if (!state) return;
    const scope = scopeKey(state.capabilities.tenant.id, state.capabilities.user.id);
    payments(scope).then(setRows);
    pendingCount(scope).then(setPending);
    syncInfo(scope).then((info) => {
      setLastSuccess(info.lastSuccess);
      setLastError(info.lastError);
    });
  }, [state]);

  useEffect(load, [load]);
  useFocusEffect(load);

  const sync = async () => {
    if (!state) return;
    setSyncing(true);
    try {
      await syncNow(
        {
          token: state.token,
          deviceId: state.deviceId,
          tenantId: state.capabilities.tenant.id,
          userId: state.capabilities.user.id,
        },
        { force: true },
      );
    } finally {
      setSyncing(false);
      load();
    }
  };

  return (
    <ListScreen>
      <FlatList
        data={rows}
        keyExtractor={(item) => item.operation_id}
        contentContainerStyle={styles.list}
        ListHeaderComponent={
          <View style={styles.header}>
            <AppHeader eyebrow="Movimientos locales y confirmados" title="Mis cobros" />
            <SurfaceCard>
              <Text variant="titleMedium">{pending} pendientes de sincronización</Text>
              <Text variant="bodySmall">
                {lastSuccess
                  ? `Última sincronización: ${new Date(lastSuccess).toLocaleString('es-AR')}`
                  : 'Todavía no hay una sincronización exitosa.'}
              </Text>
              {lastError ? <Text variant="bodySmall">Último error: {lastError}</Text> : null}
              <Button mode="outlined" loading={syncing} onPress={sync}>
                Sincronizar ahora
              </Button>
            </SurfaceCard>
            <SectionTitle title="Historial" />
          </View>
        }
        renderItem={({ item }) => (
          <SurfaceCard>
            <Text variant="titleMedium">$ {item.amount}</Text>
            <Text>{statusMessage(item)}</Text>
            <Text variant="bodySmall">
              {item.recorded_at} · operación {item.operation_id}
            </Text>
          </SurfaceCard>
        )}
        ItemSeparatorComponent={() => <View style={{ height: spacing.sm }} />}
        ListEmptyComponent={
          <EmptyState
            icon="wallet-outline"
            title="Sin cobros"
            description="Los cobros locales aparecerán aquí inmediatamente."
          />
        }
      />
    </ListScreen>
  );
}

const styles = StyleSheet.create({
  list: { padding: spacing.xl, flexGrow: 1 },
  header: { gap: spacing.md, marginBottom: spacing.md },
});
