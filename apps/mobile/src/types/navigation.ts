import type { NavigatorScreenParams } from '@react-navigation/native';
export type TabParamList = {
  Route: undefined;
  Collections: undefined;
  Profile: undefined;
};
export type RootStackParamList = {
  MainTabs: NavigatorScreenParams<TabParamList> | undefined;
  VisitDetail: { stopId: string };
  RegisterPayment: { visitId: string };
  VisitOutcome: { stopId: string };
  Settlement: undefined;
  Notifications: undefined;
};
