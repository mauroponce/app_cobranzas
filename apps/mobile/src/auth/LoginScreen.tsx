import { useState } from 'react';
import { View } from 'react-native';
import { Button, Text, TextInput } from 'react-native-paper';
import { Screen } from '../components/Screen';
import { useAuth } from './AuthContext';

export function LoginScreen() {
  const { login } = useAuth(); const [email, setEmail] = useState(''); const [password, setPassword] = useState(''); const [error, setError] = useState('');
  return <Screen><View style={{ gap: 16, paddingTop: 48 }}><Text variant="headlineMedium">App Cobranzas</Text><Text>Ingresá con tu cuenta de empresa.</Text><TextInput label="Correo electrónico" value={email} onChangeText={setEmail} autoCapitalize="none" keyboardType="email-address" /><TextInput label="Contraseña" value={password} onChangeText={setPassword} secureTextEntry /><Button mode="contained" onPress={() => login(email, password).catch((reason: unknown) => setError(reason instanceof Error ? reason.message : 'No se pudo iniciar sesión.'))}>Ingresar</Button>{error ? <Text>{error}</Text> : null}</View></Screen>;
}
