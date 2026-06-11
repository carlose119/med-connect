import React, { useEffect, useState, useCallback } from 'react';
import { View, Text, FlatList, StyleSheet } from 'react-native';
import { useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import type { HistorialStackParamList } from '../../../navigation/TabNavigator';
import { getHistory, getNotes } from '../../../services/medicalHistoryService';
import Card from '../../../components/Card';
import LoadingSpinner from '../../../components/LoadingSpinner';
import EmptyState from '../../../components/EmptyState';
import ErrorBanner from '../../../components/ErrorBanner';
import type { MedicalNote } from '../../../types/medical-history';

type NavigationProp = NativeStackNavigationProp<HistorialStackParamList, 'MedicalHistory'>;

function formatDate(iso: string): string {
  const d = new Date(iso);
  return d.toLocaleDateString('es-AR', { day: '2-digit', month: 'short', year: 'numeric' });
}

export default function MedicalHistoryScreen() {
  const navigation = useNavigation<NavigationProp>();
  const [notes, setNotes] = useState<MedicalNote[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [notFound, setNotFound] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      await getHistory();
      const notesData = await getNotes();
      setNotes(notesData);
    } catch (err: unknown) {
      const axiosErr = err as { response?: { status?: number } };
      if (axiosErr?.response?.status === 404) {
        setNotFound(true);
      } else {
        setError('No se pudo cargar el historial clínico.');
      }
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { load(); }, [load]);

  if (loading) return <LoadingSpinner />;
  if (notFound) return <EmptyState message="Aún no tenés historial clínico. Reserved una cita para que tu médico lo cree." />;
  if (error) return <ErrorBanner message={error} onRetry={load} />;

  return (
    <View style={styles.container}>
      <FlatList
        data={notes}
        keyExtractor={(item) => String(item.id)}
        renderItem={({ item }) => (
          <View style={styles.cardWrapper}>
            <Card>
              <View style={styles.noteRow}>
                <Text style={styles.noteIcon}>📋</Text>
                <View style={styles.noteContent}>
                  <Text style={styles.noteDate}>{formatDate(item.created_at)}</Text>
                  <Text style={styles.noteDoctor}>{item.doctor?.name ?? 'Doctor'}</Text>
                  <Text style={styles.noteDiagnosis} numberOfLines={2}>{item.diagnosis}</Text>
                </View>
                <Text style={styles.arrow}>›</Text>
              </View>
            </Card>
          </View>
        )}
        ListEmptyComponent={<EmptyState message="No hay notas clínicas registradas." />}
        contentContainerStyle={styles.list}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#f5f5f5' },
  list: { padding: 16 },
  cardWrapper: { marginBottom: 12 },
  noteRow: { flexDirection: 'row', alignItems: 'center' },
  noteIcon: { fontSize: 24, marginRight: 12 },
  noteContent: { flex: 1 },
  noteDate: { fontSize: 12, color: '#999', marginBottom: 2 },
  noteDoctor: { fontSize: 14, fontWeight: '600', color: '#1a1a1a', marginBottom: 2 },
  noteDiagnosis: { fontSize: 13, color: '#666' },
  arrow: { fontSize: 22, color: '#ccc', marginLeft: 8 },
});