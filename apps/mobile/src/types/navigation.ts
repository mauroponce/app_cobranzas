import type { NavigatorScreenParams } from '@react-navigation/native';
export type TabParamList = {
  Home: undefined;
  Route: undefined;
  Customers: undefined;
  Collections: undefined;
  Profile: undefined;
};
export type RootStackParamList = {
  MainTabs: NavigatorScreenParams<TabParamList> | undefined;
  CustomerDetail: { customerId: string };
  VisitDetail: { stopId: string };
  RegisterPayment: { visitId: string };
  VisitOutcome: { stopId: string };
  Settlement: undefined;
  Notifications: undefined;
};
