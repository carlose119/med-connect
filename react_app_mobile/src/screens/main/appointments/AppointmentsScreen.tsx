import React, { useEffect, useState, useCallback } from 'react';
import { View, Text, SectionList, StyleSheet, TouchableOpacity, Alert } from 'react-native';
import { useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import type { CitasStackParamList } from '../../../navigation/TabNavigator';
import { getAppointments, cancelAppointment } from '../../../services/appointmentService';
import AppointmentCard from '../../../components/AppointmentCard';
import LoadingSpinner from '../../../components/LoadingSpinner';
import EmptyState from '../../../components/EmptyState';
import ErrorBanner from '../../../components/ErrorBanner';
import type { Appointment } from '../../../types/appointment';

type NavigationProp = NativeStackNavigationProp<CitasStackParamList, 'Appointments'>;

interface Section {
  title: string;
  data: Appointment[];
}

export default function AppointmentsScreen() {
  const navigation = useNavigation<NavigationProp>();
  const [sections, setSections] = useState<Section[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const { upcoming, past } = await getAppointments();
      setSections([
        { title: 'Próximas', data: upcoming },
        { title: 'Pasadas', data: past },
      ]);
    } catch {
      setError('No se pudieron cargar las citas. Reintentar.');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { load(); }, [load]);

  const handleCancel = useCallback(async (id: number) => {
    Alert.alert(
      'Cancelar cita',
      '¿Estás seguro de que querés cancelar esta cita?',
      [
        { text: 'No', style: 'cancel' },
        {
          text: 'Sí, cancelar',
          style: 'destructive',
          onPress: async () => {
            try {
              await cancelAppointment(id);
              await load();
            } catch {
              Alert.alert('Error', 'No se pudo cancelar la cita.');
            }
          },
        },
      ],
    );
  }, [load]);

  if (loading) return <LoadingSpinner />;
  if (error) return <ErrorBanner message={error} onRetry={load} />;

  return (
    <View style={styles.container}>
      <TouchableOpacity
        style={styles.searchButton}
        onPress={() => navigation.push('DoctorList')}
        activeOpacity={0.8}
      >
        <Text style={styles.searchButtonText}>🔍 Buscar doctor</Text>
      </TouchableOpacity>

      <SectionList
        sections={sections}
        keyExtractor={(item) => String(item.id)}
        renderItem={({ item }) => (
          <View style={styles.cardWrapper}>
            <AppointmentCard appointment={item} onCancel={(id: number) => handleCancel(id)} />
          </View>
        )}
        renderSectionHeader={({ section: { title } }) => (
          <Text style={styles.sectionHeader}>{title}</Text>
        )}
        ListEmptyComponent={
          <EmptyState message="No tenés citas programadas." />
        }
        contentContainerStyle={styles.list}
        stickySectionHeadersEnabled={false}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#f5f5f5' },
  searchButton: {
    backgroundColor: '#1a73e8', borderRadius: 10, padding: 14, margin: 16, alignItems: 'center',
  },
  searchButtonText: { color: '#fff', fontWeight: '600', fontSize: 16 },
  list: { paddingHorizontal: 16, paddingBottom: 24 },
  cardWrapper: { marginBottom: 12 },
  sectionHeader: {
    fontSize: 14, fontWeight: '700', color: '#666', marginTop: 16, marginBottom: 8,
    textTransform: 'uppercase', letterSpacing: 0.5,
  },
});