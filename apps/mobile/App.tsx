import { useEffect } from 'react';
import { AppState } from 'react-native';
import * as Network from 'expo-network';
import {
  NavigationContainer,
  DarkTheme as NavigationDarkTheme,
  DefaultTheme as NavigationLightTheme,
} from '@react-navigation/native';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import { useTheme } from 'react-native-paper';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import { SafeAreaProvider, useSafeAreaInsets } from 'react-native-safe-area-context';
import { StatusBar } from 'expo-status-bar';
import { initializeDatabase } from './src/database/database';
import { WelcomeScreen } from './src/app/WelcomeScreen';
import { CustomersScreen } from './src/features/customers/CustomersScreen';
import { CustomerDetailScreen } from './src/features/customers/CustomerDetailScreen';
import { CollectionsScreen } from './src/features/collections/CollectionsScreen';
import { RegisterPaymentScreen } from './src/features/collections/RegisterPaymentScreen';
import { SettlementScreen } from './src/features/collections/SettlementScreen';
import { VisitDetailScreen } from './src/features/visits/VisitDetailScreen';
import { VisitOutcomeScreen } from './src/features/visits/VisitOutcomeScreen';
import { NotificationsScreen } from './src/features/notifications/NotificationsScreen';
import { SettingsScreen } from './src/features/settings/SettingsScreen';
import { AppearanceProvider, useAppearance } from './src/theme/AppearanceContext';
import { AuthProvider, useAuth } from './src/auth/AuthContext';
import { LoginScreen } from './src/auth/LoginScreen';
import { syncNow } from './src/sync/worker';
import type { RootStackParamList, TabParamList } from './src/types/navigation';

const Stack = createNativeStackNavigator<RootStackParamList>();
const Tabs = createBottomTabNavigator<TabParamList>();

function MainTabs() {
  const { state } = useAuth();
  const theme = useTheme();
  const insets = useSafeAreaInsets();
  return (
    <Tabs.Navigator
      screenOptions={({ route }) => ({
        headerShown: false,
        tabBarHideOnKeyboard: true,
        tabBarActiveTintColor: theme.colors.primary,
        tabBarInactiveTintColor: theme.colors.onSurfaceVariant,
        tabBarLabelStyle: { fontSize: 11, fontWeight: '700', marginTop: 3 },
        tabBarIconStyle: { width: 40, height: 32, overflow: 'visible' },
        tabBarStyle: {
          height: 58 + insets.bottom,
          paddingTop: 7,
          paddingBottom: Math.max(5, insets.bottom),
          backgroundColor: theme.colors.surface,
          borderTopColor: theme.colors.outline,
        },
        tabBarIcon: ({ color }) => {
          const icons = {
            Home: 'home-variant-outline',
            Route: 'map-marker-path',
            Customers: 'account-group-outline',
            Collections: 'wallet-outline',
            Profile: 'account-outline',
          } as const;
          return (
            <MaterialCommunityIcons
              name={icons[route.name]}
              color={color}
              size={22}
              style={{ width: 24, textAlign: 'center' }}
            />
          );
        },
      })}
    >
      <Tabs.Screen name="Home" component={WelcomeScreen} options={{ title: 'Inicio' }} />
      {state?.capabilities.permissions['customers.view'] ? <Tabs.Screen name="Customers" component={CustomersScreen} options={{ title: 'Clientes' }} /> : null}
      {state?.capabilities.permissions['payments.view'] ? <Tabs.Screen name="Collections" component={CollectionsScreen} options={{ title: 'Cobros' }} /> : null}
      <Tabs.Screen name="Profile" component={SettingsScreen} options={{ title: 'Perfil' }} />
    </Tabs.Navigator>
  );
}

function AppNavigation() {
  const { state, ready } = useAuth();
  const { isDark } = useAppearance();
  const theme = useTheme();
  const navTheme = isDark ? NavigationDarkTheme : NavigationLightTheme;
  useEffect(() => {
    if (!state) return undefined;
    const run = () => syncNow({ token: state.token, deviceId: state.deviceId, tenantId: state.capabilities.tenant.id, userId: state.capabilities.user.id }).catch(() => {});
    run();
    const network = Network.addNetworkStateListener((event) => { if (event.isConnected && event.isInternetReachable !== false) run(); });
    const appState = AppState.addEventListener('change', (next) => { if (next === 'active') run(); });
    return () => { network.remove(); appState.remove(); };
  }, [state]);
  if (!ready) return null;
  if (!state) return <LoginScreen />;
  return (
    <NavigationContainer
      theme={{
        ...navTheme,
        colors: {
          ...navTheme.colors,
          primary: theme.colors.primary,
          background: theme.colors.background,
          card: theme.colors.surface,
          text: theme.colors.onSurface,
          border: theme.colors.outline,
        },
      }}
    >
      <StatusBar style={isDark ? 'light' : 'dark'} />
      <Stack.Navigator screenOptions={{ headerShown: false }}>
        <Stack.Screen name="MainTabs" component={MainTabs} />
        <Stack.Screen name="CustomerDetail" component={CustomerDetailScreen} />
        <Stack.Screen name="VisitDetail" component={VisitDetailScreen} />
        <Stack.Screen name="RegisterPayment" component={RegisterPaymentScreen} />
        <Stack.Screen name="VisitOutcome" component={VisitOutcomeScreen} />
        <Stack.Screen name="Settlement" component={SettlementScreen} />
        <Stack.Screen name="Notifications" component={NotificationsScreen} />
      </Stack.Navigator>
    </NavigationContainer>
  );
}
export default function App() {
  useEffect(() => {
    initializeDatabase().catch((error: unknown) =>
      console.error('Could not initialize local database', error),
    );
  }, []);
  return (
    <SafeAreaProvider>
      <AppearanceProvider>
        <AuthProvider><AppNavigation /></AuthProvider>
      </AppearanceProvider>
    </SafeAreaProvider>
  );
}
