import React, { useEffect, useState, useCallback } from 'react';
import { View, Text, StyleSheet, ScrollView, ActivityIndicator } from 'react-native';
import { useRoute, useNavigation } from '@react-navigation/native';
import type { RouteProp } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import type { CitasStackParamList } from '../../../navigation/TabNavigator';
import { getDoctor } from '../../../services/doctorService';
import Button from '../../../components/Button';
import Card from '../../../components/Card';
import ErrorBanner from '../../../components/ErrorBanner';
import type { Doctor } from '../../../types/doctor';

type RouteProps = RouteProp<CitasStackParamList, 'DoctorDetail'>;
type NavigationProp = NativeStackNavigationProp<CitasStackParamList, 'DoctorDetail'>;

export default function DoctorDetailScreen() {
  const route = useRoute<RouteProps>();
  const navigation = useNavigation<NavigationProp>();
  const { doctorId } = route.params;

  const [doctor, setDoctor] = useState<Doctor | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const data = await getDoctor(doctorId);
      setDoctor(data);
    } catch {
      setError('No se pudo cargar la información del doctor.');
    } finally {
      setLoading(false);
    }
  }, [doctorId]);

  useEffect(() => { load(); }, [load]);

  if (loading) return <View style={styles.center}><ActivityIndicator size="large" color="#1a73e8" /></View>;
  if (error) return <ErrorBanner message={error} onRetry={load} />;
  if (!doctor) return null;

  const initials = doctor.name.split(' ').map((n: string) => n[0]).join('').slice(0, 2).toUpperCase();

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content}>
      <Card>
        <View style={styles.avatarRow}>
          <View style={styles.avatar}>
            <Text style={styles.avatarText}>{initials}</Text>
          </View>
          <View style={styles.nameRow}>
            <Text style={styles.name}>{doctor.name}</Text>
            <Text style={styles.specialty}>{doctor.specialty?.name}</Text>
          </View>
        </View>
        {doctor.bio ? (
          <Text style={styles.bio}>{doctor.bio}</Text>
        ) : null}
        {doctor.license_number ? (
          <Text style={styles.license}>Matrícula: {doctor.license_number}</Text>
        ) : null}
      </Card>

      <View style={styles.buttonWrapper}>
        <Button
          title="Ver disponibilidad"
          onPress={() => navigation.push('DoctorAvailability', { doctorId, doctorName: doctor.name })}
          variant="primary"
        />
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#f5f5f5' },
  content: { padding: 16 },
  center: { flex: 1, justifyContent: 'center', alignItems: 'center' },
  avatarRow: { flexDirection: 'row', alignItems: 'center', marginBottom: 16 },
  avatar: {
    width: 56, height: 56, borderRadius: 28, backgroundColor: '#e3f2fd',
    justifyContent: 'center', alignItems: 'center',
  },
  avatarText: { color: '#1e40af', fontSize: 20, fontWeight: '700' },
  nameRow: { marginLeft: 14, flex: 1 },
  name: { fontSize: 18, fontWeight: '700', color: '#1a1a1a' },
  specialty: { fontSize: 14, color: '#666', marginTop: 2 },
  bio: { fontSize: 15, color: '#444', lineHeight: 22, marginBottom: 12 },
  license: { fontSize: 13, color: '#999', fontStyle: 'italic' },
  buttonWrapper: { marginTop: 20 },
});