import React, { useCallback } from 'react';
import { View, Text, StyleSheet, Alert } from 'react-native';
import { useAuth } from '../../../contexts/AuthContext';
import { logout } from '../../../services/authService';
import Card from '../../../components/Card';
import Button from '../../../components/Button';

function formatDate(iso?: string | null): string {
  if (!iso) return '-';
  const d = new Date(iso);
  return d.toLocaleDateString('es-AR', { day: '2-digit', month: 'long', year: 'numeric' });
}

export default function ProfileScreen() {
  const { user } = useAuth();

  const handleLogout = useCallback(async () => {
    Alert.alert(
      'Cerrar sesión',
      '¿Estás seguro de que querés cerrar tu sesión?',
      [
        { text: 'Cancelar', style: 'cancel' },
        {
          text: 'Sí, cerrar',
          style: 'destructive',
          onPress: async () => {
            await logout();
          },
        },
      ],
    );
  }, []);

  const initials = user?.name
    ?.split(' ')
    .map((n: string) => n[0])
    .join('')
    .slice(0, 2)
    .toUpperCase() ?? '?';

  return (
    <View style={styles.container}>
      <Card>
        <View style={styles.avatarRow}>
          <View style={styles.avatar}>
            <Text style={styles.avatarText}>{initials}</Text>
          </View>
        </View>

        <View style={styles.field}>
          <Text style={styles.fieldLabel}>Nombre</Text>
          <Text style={styles.fieldValue}>{user?.name ?? '-'}</Text>
        </View>
        <View style={styles.field}>
          <Text style={styles.fieldLabel}>Email</Text>
          <Text style={styles.fieldValue}>{user?.email ?? '-'}</Text>
        </View>
        <View style={styles.field}>
          <Text style={styles.fieldLabel}>Rol</Text>
          <Text style={styles.fieldValue}>Paciente</Text>
        </View>
        <View style={styles.field}>
          <Text style={styles.fieldLabel}>Registrado</Text>
          <Text style={styles.fieldValue}>{formatDate(user?.created_at)}</Text>
        </View>
      </Card>

      <View style={styles.buttonWrapper}>
        <Button
          title="Cerrar sesión"
          onPress={handleLogout}
          variant="danger"
        />
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#f5f5f5', padding: 16 },
  avatarRow: { alignItems: 'center', marginBottom: 24 },
  avatar: {
    width: 72, height: 72, borderRadius: 36, backgroundColor: '#e3f2fd',
    justifyContent: 'center', alignItems: 'center',
  },
  avatarText: { color: '#1e40af', fontSize: 28, fontWeight: '700' },
  field: { marginBottom: 16 },
  fieldLabel: { fontSize: 12, color: '#999', marginBottom: 4, textTransform: 'uppercase', letterSpacing: 0.5 },
  fieldValue: { fontSize: 16, color: '#1a1a1a' },
  buttonWrapper: { marginTop: 32 },
});