import apiClient from './apiClient';
import type { Appointment, AppointmentCreate } from '../types/appointment';

export async function getAppointments(): Promise<{ upcoming: Appointment[]; past: Appointment[] }> {
  const response = await apiClient.get<{ upcoming: Appointment[]; past: Appointment[] }>('/api/v1/appointments');
  return response.data;
}

export async function getAppointment(id: number): Promise<Appointment> {
  const response = await apiClient.get<{ data: Appointment }>(`/api/v1/appointments/${id}`);
  return response.data.data;
}

export async function bookAppointment(data: AppointmentCreate): Promise<Appointment> {
  const response = await apiClient.post<{ data: Appointment }>('/api/v1/appointments', data);
  return response.data.data;
}

export async function cancelAppointment(id: number): Promise<void> {
  await apiClient.delete(`/api/v1/appointments/${id}`);
}