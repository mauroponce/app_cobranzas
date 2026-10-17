import { useCallback, useEffect, useState } from 'react';
import { useFocusEffect } from '@react-navigation/native';
import type { NativeStackScreenProps } from '@react-navigation/native-stack';
import { Button, Text } from 'react-native-paper';
import { useAuth } from '../../auth/AuthContext';
import { AppHeader } from '../../components/AppHeader';
import { BalanceCard } from '../../components/BalanceCard';
import { Screen } from '../../components/Screen';
import { SurfaceCard } from '../../components/SurfaceCard';
import { formatMoney } from '../../data/collectorData';
import { pendingCount, route, scopeKey, type LocalRoute } from '../../database/financial';
import { apiPost } from '../../services/api/client';
import { syncNow } from '../../sync/worker';
import type { RootStackParamList } from '../../types/navigation';

type Props = NativeStackScreenProps<RootStackParamList, 'Settlement'>;

export function SettlementScreen({ navigation }: Props) {
  const { state } = useAuth();
  const [activeRoute, setActiveRoute] = useState<LocalRoute | null>(null);
  const [pending, setPending] = useState(0);
  const [submitting, setSubmitting] = useState(false);
  const [message, setMessage] = useState('');
  const load = useCallback(() => {
    if (!state) return;
    const scope = scopeKey(state.capabilities.tenant.id, state.capabilities.user.id);
    route(scope).then(setActiveRoute);
    pendingCount(scope).then(setPending);
  }, [state]);
  useEffect(load, [load]);
  useFocusEffect(load);

  const submit = async () => {
    if (!state || !activeRoute || activeRoute.status !== 'in_progress') return;
    setSubmitting(true);
    setMessage('');
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
      const scope = scopeKey(state.capabilities.tenant.id, state.capabilities.user.id);
      const remaining = await pendingCount(scope);
      if (remaining > 0) {
        setPending(remaining);
        setMessage(
          'Todavía hay operaciones locales sin aceptación del servidor. No se presentó la hoja.',
        );
        return;
      }
      await apiPost(
        `/collection-routes/${activeRoute.id}/settlement`,
        { local_queue_empty: true },
        state.token,
      );
      await syncNow(
        {
          token: state.token,
          deviceId: state.deviceId,
          tenantId: state.capabilities.tenant.id,
          userId: state.capabilities.user.id,
        },
        { force: true },
      );
      load();
      setMessage('Rendición presentada. Administración debe recibir y conciliar el efectivo.');
    } catch (error) {
      setMessage(error instanceof Error ? error.message : 'No se pudo presentar la rendición.');
    } finally {
      setSubmitting(false);
    }
  };

  const declared = activeRoute?.declared_cash_cents ?? '0';
  return (
    <Screen>
      <AppHeader eyebrow="Efectivo del cobrador" title="Rendición" onBack={navigation.goBack} />
      <BalanceCard
        label="Efectivo declarado en la hoja"
        amount={formatMoney(declared)}
        footer={
          activeRoute
            ? `Estado: ${activeRoute.settlement_status ?? 'sin presentar'}`
            : 'Sin hoja disponible'
        }
      />
      <SurfaceCard>
        <Text variant="titleMedium">Resumen</Text>
        <Text>Operaciones locales pendientes: {pending}</Text>
        <Text>
          Efectivo físicamente recibido: {formatMoney(activeRoute?.received_cash_cents ?? null)}
        </Text>
        <Text>Las transferencias se verifican por separado y no integran esta rendición.</Text>
      </SurfaceCard>
      {message ? <Text>{message}</Text> : null}
      <Button
        mode="contained"
        disabled={!activeRoute || activeRoute.status !== 'in_progress' || pending > 0 || submitting}
        loading={submitting}
        onPress={submit}
      >
        Presentar rendición de la hoja
      </Button>
    </Screen>
  );
}
