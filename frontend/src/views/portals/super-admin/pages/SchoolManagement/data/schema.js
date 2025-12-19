import { z } from 'zod'

export const schoolSchema = z.object({
  id: z.string(),
  schoolName: z.string(),
  schoolCode: z.string(),
  schoolType: z.enum(['public', 'private', 'charter']),
  status: z.enum(['active', 'inactive', 'pending']),
  address: z.string(),
  contactEmail: z.string().email(),
  contactPhone: z.string(),
  principalName: z.string(),
  totalStudents: z.number(),
  totalTeachers: z.number(),
  createdAt: z.date(),
  updatedAt: z.date(),
})

export const schoolFormSchema = z.object({
  schoolName: z.string().min(2, 'School name must be at least 2 characters'),
  schoolCode: z.string().min(2, 'School code must be at least 2 characters'),
  schoolType: z.enum(['public', 'private', 'charter'], {
    required_error: 'Please select a school type',
  }),
  status: z.enum(['active', 'inactive', 'pending'], {
    required_error: 'Please select a status',
  }),
  address: z.string().min(5, 'Address must be at least 5 characters'),
  contactEmail: z.string().email('Please enter a valid email address'),
  contactPhone: z.string().min(10, 'Phone number must be at least 10 characters'),
  principalName: z.string().min(2, 'Principal name must be at least 2 characters'),
  totalStudents: z.coerce.number().min(0, 'Must be a positive number'),
  totalTeachers: z.coerce.number().min(0, 'Must be a positive number'),
})
