import React from 'react';
import { View, Text, StyleSheet } from 'react-native';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import { useAuth } from '../contexts/AuthContext';

type MainStackParamList = {
  Home: undefined;
};

const Stack = createNativeStackNavigator<MainStackParamList>();

function HomeScreen() {
  const { user } = useAuth();

  return (
    <View style={styles.container}>
      <Text style={styles.welcome}>Bienvenido, {user?.name ?? 'Usuario'}</Text>
      <Text style={styles.subtitle}>Ya estás autenticado en MedConnect.</Text>
    </View>
  );
}

export default function MainNavigator() {
  return (
    <Stack.Navigator
      screenOptions={{
        headerShown: false,
      }}
    >
      <Stack.Screen name="Home" component={HomeScreen} />
    </Stack.Navigator>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f5f5f5',
    justifyContent: 'center',
    alignItems: 'center',
    paddingHorizontal: 24,
  },
  welcome: {
    fontSize: 24,
    fontWeight: '700',
    color: '#1a73e8',
    marginBottom: 8,
    textAlign: 'center',
  },
  subtitle: {
    fontSize: 16,
    color: '#666',
    textAlign: 'center',
  },
});