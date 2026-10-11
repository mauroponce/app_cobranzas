import { View, StyleSheet } from 'react-native';
import { Text, Button } from 'react-native-paper';
export function SectionTitle({
  title,
  action,
  onAction,
}: {
  title: string;
  action?: string;
  onAction?: () => void;
}) {
  return (
    <View style={styles.row}>
      <Text variant="titleMedium" style={styles.title}>
        {title}
      </Text>
      {action && onAction && (
        <Button compact onPress={onAction}>
          {action}
        </Button>
      )}
    </View>
  );
}
const styles = StyleSheet.create({
  row: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', minWidth: 0 },
  title: { fontWeight: '800', flexShrink: 1 },
});
