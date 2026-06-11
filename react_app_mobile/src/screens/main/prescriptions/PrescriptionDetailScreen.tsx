import React, { useEffect, useState, useCallback } from 'react';
import { View, Text, StyleSheet, ScrollView, Linking, Alert } from 'react-native';
import { useRoute } from '@react-navigation/native';
import type { RouteProp } from '@react-navigation/native';
import type { RecetasStackParamList } from '../../../navigation/TabNavigator';
import { getPrescriptionDetail, getPrescriptionPdfUrl } from '../../../services/prescriptionService';
import Card from '../../../components/Card';
import StatusBadge from '../../../components/StatusBadge';
import Button from '../../../components/Button';
import LoadingSpinner from '../../../components/LoadingSpinner';
import ErrorBanner from '../../../components/ErrorBanner';
import type { Prescription } from '../../../types/prescription';

type RouteProps = RouteProp<RecetasStackParamList, 'PrescriptionDetail'>;

function formatDate(iso: string): string {
  const d = new Date(iso);
  return d.toLocaleDateString('es-AR', { day: '2-digit', month: 'long', year: 'numeric' });
}

export default function PrescriptionDetailScreen() {
  const route = useRoute<RouteProps>();
  const { prescriptionId } = route.params;

  const [prescription, setPrescription] = useState<Prescription | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const data = await getPrescriptionDetail(prescriptionId);
      setPrescription(data);
    } catch {
      setError('No se pudo cargar la receta.');
    } finally {
      setLoading(false);
    }
  }, [prescriptionId]);

  useEffect(() => { load(); }, [load]);

  const handleOpenPdf = async () => {
    const url = getPrescriptionPdfUrl(prescriptionId);
    try {
      const supported = await Linking.canOpenURL(url);
      if (supported) {
        await Linking.openURL(url);
      } else {
        Alert.alert('Error', 'No se pudo abrir el PDF.');
      }
    } catch {
      Alert.alert('Error', 'No se pudo abrir el PDF.');
    }
  };

  if (loading) return <LoadingSpinner />;
  if (error) return <ErrorBanner message={error} onRetry={load} />;
  if (!prescription) return null;

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content}>
      <Card>
        <View style={styles.header}>
          <View>
            <Text style={styles.code}>{prescription.unique_code}</Text>
            <Text style={styles.doctor}>{prescription.doctor?.name}</Text>
            <Text style={styles.date}>Emitida: {formatDate(prescription.issued_at)}</Text>
          </View>
          <StatusBadge status={prescription.status} />
        </View>
      </Card>

      <Text style={styles.sectionTitle}>Medicamentos</Text>
      {prescription.items.map((item, idx) => (
        <Card key={item.id ?? idx} style={{ marginBottom: 8 }}>
          <Text style={styles.medicine}>{item.medicine}</Text>
          <View style={styles.itemRow}>
            <Text style={styles.itemLabel}>Dosis:</Text>
            <Text style={styles.itemValue}>{item.dosage}</Text>
          </View>
          <View style={styles.itemRow}>
            <Text style={styles.itemLabel}>Frecuencia:</Text>
            <Text style={styles.itemValue}>{item.frequency}</Text>
          </View>
          {item.duration ? (
            <View style={styles.itemRow}>
              <Text style={styles.itemLabel}>Duración:</Text>
              <Text style={styles.itemValue}>{item.duration}</Text>
            </View>
          ) : null}
          {item.instructions ? (
            <Text style={styles.instructions}>{item.instructions}</Text>
          ) : null}
        </Card>
      ))}

      <View style={styles.pdfButton}>
        <Button
          title="Ver PDF de la receta"
          onPress={handleOpenPdf}
          variant="primary"
        />
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#f5f5f5' },
  content: { padding: 16 },
  header: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-start' },
  code: { fontSize: 16, fontWeight: '700', color: '#1a1a1a', marginBottom: 4 },
  doctor: { fontSize: 14, color: '#555', marginBottom: 4 },
  date: { fontSize: 12, color: '#999' },
  sectionTitle: { fontSize: 14, fontWeight: '700', color: '#666', marginTop: 20, marginBottom: 8, textTransform: 'uppercase', letterSpacing: 0.5 },
  medicine: { fontSize: 15, fontWeight: '600', color: '#1a1a1a', marginBottom: 8 },
  itemRow: { flexDirection: 'row', marginBottom: 4 },
  itemLabel: { fontSize: 13, color: '#666', width: 80 },
  itemValue: { fontSize: 13, color: '#333', flex: 1 },
  instructions: { fontSize: 13, color: '#666', fontStyle: 'italic', marginTop: 6 },
  pdfButton: { marginTop: 24, marginBottom: 32 },
});