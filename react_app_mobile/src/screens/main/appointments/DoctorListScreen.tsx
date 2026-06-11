import React, { useEffect, useState, useCallback } from 'react';
import { View, Text, FlatList, StyleSheet, TouchableOpacity, ScrollView } from 'react-native';
import { useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import type { CitasStackParamList } from '../../../navigation/TabNavigator';
import { getDoctors, getSpecialties } from '../../../services/doctorService';
import DoctorCard from '../../../components/DoctorCard';
import LoadingSpinner from '../../../components/LoadingSpinner';
import EmptyState from '../../../components/EmptyState';
import ErrorBanner from '../../../components/ErrorBanner';
import type { Doctor, Specialty } from '../../../types/doctor';

type NavigationProp = NativeStackNavigationProp<CitasStackParamList, 'DoctorList'>;

export default function DoctorListScreen() {
  const navigation = useNavigation<NavigationProp>();
  const [doctors, setDoctors] = useState<Doctor[]>([]);
  const [specialties, setSpecialties] = useState<Specialty[]>([]);
  const [selectedSpecialtyId, setSelectedSpecialtyId] = useState<number | undefined>(undefined);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async (specialtyId?: number) => {
    setLoading(true);
    setError(null);
    try {
      const [doctorsData, specialtiesData] = await Promise.all([
        getDoctors(specialtyId),
        getSpecialties(),
      ]);
      setDoctors(doctorsData);
      setSpecialties(specialtiesData);
    } catch {
      setError('No se pudieron cargar los doctores.');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { load(); }, [load]);

  const handleSpecialtyPress = useCallback((id: number | undefined) => {
    setSelectedSpecialtyId(id);
    load(id);
  }, [load]);

  if (loading && doctors.length === 0) return <LoadingSpinner />;
  if (error && doctors.length === 0) return <ErrorBanner message={error} onRetry={() => load(selectedSpecialtyId)} />;

  return (
    <View style={styles.container}>
      <ScrollView horizontal showsHorizontalScrollIndicator={false} style={styles.chipScroll}>
        <TouchableOpacity
          style={[styles.chip, !selectedSpecialtyId && styles.chipActive]}
          onPress={() => handleSpecialtyPress(undefined)}
        >
          <Text style={[styles.chipText, !selectedSpecialtyId && styles.chipTextActive]}>Todos</Text>
        </TouchableOpacity>
        {specialties.map((s) => (
          <TouchableOpacity
            key={s.id}
            style={[styles.chip, selectedSpecialtyId === s.id && styles.chipActive]}
            onPress={() => handleSpecialtyPress(s.id)}
          >
            <Text style={[styles.chipText, selectedSpecialtyId === s.id && styles.chipTextActive]}>{s.name}</Text>
          </TouchableOpacity>
        ))}
      </ScrollView>

      {error ? <ErrorBanner message={error} onRetry={() => load(selectedSpecialtyId)} /> : null}

      <FlatList
        data={doctors}
        keyExtractor={(item) => String(item.id)}
        renderItem={({ item }) => (
          <View style={styles.cardWrapper}>
            <DoctorCard
              doctor={item}
              onPress={(doctor: Doctor) => navigation.push('DoctorDetail', { doctorId: doctor.id })}
            />
          </View>
        )}
        ListEmptyComponent={<EmptyState message="No hay doctores disponibles." />}
        contentContainerStyle={styles.list}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#f5f5f5' },
  chipScroll: { backgroundColor: '#fff', paddingVertical: 12, paddingHorizontal: 8 },
  chip: {
    paddingHorizontal: 14, paddingVertical: 6, borderRadius: 16, borderWidth: 1, borderColor: '#ddd',
    marginHorizontal: 4, backgroundColor: '#fff',
  },
  chipActive: { backgroundColor: '#1a73e8', borderColor: '#1a73e8' },
  chipText: { fontSize: 14, color: '#555' },
  chipTextActive: { color: '#fff', fontWeight: '600' },
  list: { padding: 16 },
  cardWrapper: { marginBottom: 12 },
});