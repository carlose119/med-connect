import apiClient from './apiClient';
import type { Doctor, DoctorAvailability, Specialty } from '../types/doctor';

export interface DoctorListResponse {
  data: Doctor[];
  links?: Record<string, string>;
  meta?: Record<string, number>;
}

export async function getDoctors(specialtyId?: number): Promise<Doctor[]> {
  const params = specialtyId ? { specialty_id: specialtyId } : undefined;
  const response = await apiClient.get<DoctorListResponse>('/api/v1/doctors', { params });
  return response.data.data ?? [];
}

export async function getDoctor(id: number): Promise<Doctor> {
  const response = await apiClient.get<{ data: Doctor }>(`/api/v1/doctors/${id}`);
  return response.data.data;
}

export async function getSpecialties(): Promise<Specialty[]> {
  const response = await apiClient.get<{ data: Specialty[] }>('/api/v1/specialties');
  return response.data.data ?? [];
}

export async function getAvailability(doctorId: number, date: string): Promise<DoctorAvailability[]> {
  const tz = 'America/Argentina/Buenos_Aires';
  const response = await apiClient.get<DoctorAvailability[]>(
    `/api/v1/doctors/${doctorId}/availability`,
    { params: { date, tz } },
  );
  return response.data;
}