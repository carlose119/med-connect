export interface MedicalHistory {
  id: number;
  patient_id: number;
  primary_doctor_id?: number;
  opened_at: string;
  notes_count?: number;
}

export interface MedicalNote {
  id: number;
  medical_history_id: number;
  doctor: { id: number; name: string };
  symptoms: string;
  physical_exam: string;
  diagnosis: string;
  treatment_notes: string;
  corrects_note_id?: number;
  created_at: string;
}