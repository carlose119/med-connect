import React, { useEffect, useState, useCallback } from 'react';
import { View, Text, StyleSheet, ScrollView } from 'react-native';
import { useRoute } from '@react-navigation/native';
import type { RouteProp } from '@react-navigation/native';
import type { HistorialStackParamList } from '../../../navigation/TabNavigator';
import { getNoteDetail } from '../../../services/medicalHistoryService';
import Card from '../../../components/Card';
import LoadingSpinner from '../../../components/LoadingSpinner';
import ErrorBanner from '../../../components/ErrorBanner';
import type { MedicalNote } from '../../../types/medical-history';

type RouteProps = RouteProp<HistorialStackParamList, 'MedicalNoteDetail'>;

function formatDate(iso: string): string {
  const d = new Date(iso);
  return d.toLocaleDateString('es-AR', { day: '2-digit', month: 'long', year: 'numeric' });
}

interface FieldProps { label: string; value?: string; }
function NoteField({ label, value }: FieldProps) {
  if (!value) return null;
  return (
    <View style={styles.field}>
      <Text style={styles.fieldLabel}>{label}</Text>
      <Text style={styles.fieldValue}>{value}</Text>
    </View>
  );
}

export default function MedicalNoteDetailScreen() {
  const route = useRoute<RouteProps>();
  const { noteId } = route.params;

  const [note, setNote] = useState<MedicalNote | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const data = await getNoteDetail(noteId);
      setNote(data);
    } catch {
      setError('No se pudo cargar la nota clínica.');
    } finally {
      setLoading(false);
    }
  }, [noteId]);

  useEffect(() => { load(); }, [load]);

  if (loading) return <LoadingSpinner />;
  if (error) return <ErrorBanner message={error} onRetry={load} />;
  if (!note) return null;

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content}>
      <Card>
        <Text style={styles.date}>{formatDate(note.created_at)}</Text>
        <Text style={styles.doctor}>{note.doctor?.name ?? 'Doctor'}</Text>
      </Card>

      <Card style={{ marginTop: 12 }}>
        <NoteField label="Motivo de consulta" value={note.symptoms} />
        <NoteField label="Examen físico" value={note.physical_exam} />
        <NoteField label="Diagnóstico" value={note.diagnosis} />
        <NoteField label="Plan de tratamiento" value={note.treatment_notes} />
      </Card>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#f5f5f5' },
  content: { padding: 16 },
  date: { fontSize: 13, color: '#999', marginBottom: 4 },
  doctor: { fontSize: 16, fontWeight: '600', color: '#1a1a1a' },
  field: { marginBottom: 16 },
  fieldLabel: { fontSize: 12, fontWeight: '600', color: '#666', marginBottom: 4, textTransform: 'uppercase', letterSpacing: 0.5 },
  fieldValue: { fontSize: 15, color: '#333', lineHeight: 22 },
});