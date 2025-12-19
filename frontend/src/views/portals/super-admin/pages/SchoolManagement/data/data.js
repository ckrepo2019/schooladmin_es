import { GraduationCap, Building2, School2 } from 'lucide-react'

export const schoolTypes = [
  {
    value: 'public',
    label: 'Public',
    icon: School2,
  },
  {
    value: 'private',
    label: 'Private',
    icon: Building2,
  },
  {
    value: 'charter',
    label: 'Charter',
    icon: GraduationCap,
  },
]

export const statuses = [
  {
    value: 'active',
    label: 'Active',
    color: 'bg-teal-500/10 text-teal-700 dark:text-teal-400',
  },
  {
    value: 'inactive',
    label: 'Inactive',
    color: 'bg-gray-500/10 text-gray-700 dark:text-gray-400',
  },
  {
    value: 'pending',
    label: 'Pending',
    color: 'bg-yellow-500/10 text-yellow-700 dark:text-yellow-400',
  },
]
