import apiClient from './apiClient';
import type { MedicalHistory, MedicalNote } from '../types/medical-history';

export async function getHistory(): Promise<MedicalHistory> {
  const response = await apiClient.get<{ data: MedicalHistory }>('/api/v1/medical-history');
  return response.data.data;
}

export async function getNotes(): Promise<MedicalNote[]> {
  const response = await apiClient.get<{ data: MedicalNote[] }>('/api/v1/medical-history/notes');
  return response.data.data ?? [];
}

export async function getNoteDetail(id: number): Promise<MedicalNote> {
  const response = await apiClient.get<{ data: MedicalNote }>(`/api/v1/medical-history/notes/${id}`);
  return response.data.data;
}