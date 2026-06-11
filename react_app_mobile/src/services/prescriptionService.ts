import apiClient from './apiClient';
import type { Prescription } from '../types/prescription';

export async function getPrescriptions(): Promise<Prescription[]> {
  const response = await apiClient.get<{ data: Prescription[] }>('/api/v1/prescriptions');
  return response.data.data ?? [];
}

export async function getPrescriptionDetail(id: number): Promise<Prescription> {
  const response = await apiClient.get<{ data: Prescription }>(`/api/v1/prescriptions/${id}`);
  return response.data.data;
}

export function getPrescriptionPdfUrl(id: number): string {
  const base = String(
    (globalThis as { process?: { env?: Record<string, string> } })
      .process?.env?.EXPO_PUBLIC_API_URL ?? 'http://localhost:8000',
  );
  return `${base}/api/v1/prescriptions/${id}/pdf`;
}