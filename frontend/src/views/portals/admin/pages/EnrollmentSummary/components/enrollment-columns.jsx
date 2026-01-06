import { ArrowUpDown } from 'lucide-react';
import { Button } from '../../../../../../components/ui/button';
import { Badge } from '../../../../../../components/ui/badge';
import { Checkbox } from '../../../../../../components/ui/checkbox';

const getStatusVariant = (status) => {
  const statusMap = {
    ENROLLED: 'default',
    'LATE ENROLLMENT': 'secondary',
    'DROPPED OUT': 'destructive',
    WITHDRAWN: 'outline',
    'TRANSFERRED IN': 'default',
    'TRANSFERRED OUT': 'outline',
  };
  return statusMap[status] || 'outline';
};

const getGenderBadge = (gender) => {
  if (!gender) return null;
  const color = gender.toLowerCase() === 'male' ? 'bg-blue-100 text-blue-800' : 'bg-pink-100 text-pink-800';
  return <Badge className={color} variant="outline">{gender}</Badge>;
};

// Common columns for all levels
const commonColumns = [
  {
    id: 'select',
    header: ({ table }) => (
      <Checkbox
        checked={
          table.getIsAllPageRowsSelected() ||
          (table.getIsSomePageRowsSelected() && 'indeterminate')
        }
        onCheckedChange={(value) => table.toggleAllPageRowsSelected(!!value)}
        aria-label="Select all"
      />
    ),
    cell: ({ row }) => (
      <Checkbox
        checked={row.getIsSelected()}
        onCheckedChange={(value) => row.toggleSelected(!!value)}
        aria-label="Select row"
      />
    ),
    enableSorting: false,
    enableHiding: false,
  },
  {
    accessorKey: 'student_number',
    header: ({ column }) => {
      return (
        <Button
          variant="ghost"
          onClick={() => column.toggleSorting(column.getIsSorted() === 'asc')}
        >
          Student Number
          <ArrowUpDown className="ml-2 h-4 w-4" />
        </Button>
      );
    },
    cell: ({ row }) => <div className="font-mono text-sm">{row.getValue('student_number')}</div>,
  },
  {
    accessorKey: 'full_name',
    header: ({ column }) => {
      return (
        <Button
          variant="ghost"
          onClick={() => column.toggleSorting(column.getIsSorted() === 'asc')}
        >
          Full Name
          <ArrowUpDown className="ml-2 h-4 w-4" />
        </Button>
      );
    },
    cell: ({ row }) => <div className="font-medium">{row.getValue('full_name')}</div>,
  },
  {
    accessorKey: 'gender',
    header: 'Gender',
    cell: ({ row }) => getGenderBadge(row.getValue('gender')),
  },
];

// Grade School specific columns
const gradeSchoolColumns = [
  ...commonColumns,
  {
    accessorKey: 'grade_level',
    header: ({ column }) => {
      return (
        <Button
          variant="ghost"
          onClick={() => column.toggleSorting(column.getIsSorted() === 'asc')}
        >
          Grade Level
          <ArrowUpDown className="ml-2 h-4 w-4" />
        </Button>
      );
    },
    cell: ({ row }) => <div>{row.getValue('grade_level')}</div>,
  },
  {
    accessorKey: 'section',
    header: 'Section',
    cell: ({ row }) => <div>{row.getValue('section')}</div>,
  },
  {
    accessorKey: 'status',
    header: 'Status',
    cell: ({ row }) => {
      const status = row.getValue('status');
      return <Badge variant={getStatusVariant(status)}>{status}</Badge>;
    },
  },
  {
    accessorKey: 'dateenrolled',
    header: ({ column }) => {
      return (
        <Button
          variant="ghost"
          onClick={() => column.toggleSorting(column.getIsSorted() === 'asc')}
        >
          Date Enrolled
          <ArrowUpDown className="ml-2 h-4 w-4" />
        </Button>
      );
    },
    cell: ({ row }) => {
      const date = row.getValue('dateenrolled');
      return date ? new Date(date).toLocaleDateString() : 'N/A';
    },
  },
];

// SHS specific columns
const shsColumns = [
  ...commonColumns,
  {
    accessorKey: 'grade_level',
    header: ({ column }) => {
      return (
        <Button
          variant="ghost"
          onClick={() => column.toggleSorting(column.getIsSorted() === 'asc')}
        >
          Grade Level
          <ArrowUpDown className="ml-2 h-4 w-4" />
        </Button>
      );
    },
    cell: ({ row }) => <div>{row.getValue('grade_level')}</div>,
  },
  {
    accessorKey: 'strand',
    header: ({ column }) => {
      return (
        <Button
          variant="ghost"
          onClick={() => column.toggleSorting(column.getIsSorted() === 'asc')}
        >
          Strand
          <ArrowUpDown className="ml-2 h-4 w-4" />
        </Button>
      );
    },
    cell: ({ row }) => (
      <Badge variant="secondary" className="font-mono">
        {row.getValue('strand')}
      </Badge>
    ),
  },
  {
    accessorKey: 'section',
    header: 'Section',
    cell: ({ row }) => <div>{row.getValue('section')}</div>,
  },
  {
    accessorKey: 'status',
    header: 'Status',
    cell: ({ row }) => {
      const status = row.getValue('status');
      return <Badge variant={getStatusVariant(status)}>{status}</Badge>;
    },
  },
  {
    accessorKey: 'dateenrolled',
    header: ({ column }) => {
      return (
        <Button
          variant="ghost"
          onClick={() => column.toggleSorting(column.getIsSorted() === 'asc')}
        >
          Date Enrolled
          <ArrowUpDown className="ml-2 h-4 w-4" />
        </Button>
      );
    },
    cell: ({ row }) => {
      const date = row.getValue('dateenrolled');
      return date ? new Date(date).toLocaleDateString() : 'N/A';
    },
  },
];

// College specific columns
const collegeColumns = [
  ...commonColumns,
  {
    accessorKey: 'year_level',
    header: ({ column }) => {
      return (
        <Button
          variant="ghost"
          onClick={() => column.toggleSorting(column.getIsSorted() === 'asc')}
        >
          Year Level
          <ArrowUpDown className="ml-2 h-4 w-4" />
        </Button>
      );
    },
    cell: ({ row }) => (
      <Badge variant="outline" className="font-medium">
        {row.getValue('year_level')}
      </Badge>
    ),
  },
  {
    accessorKey: 'course',
    header: ({ column }) => {
      return (
        <Button
          variant="ghost"
          onClick={() => column.toggleSorting(column.getIsSorted() === 'asc')}
        >
          Course
          <ArrowUpDown className="ml-2 h-4 w-4" />
        </Button>
      );
    },
    cell: ({ row }) => <div className="max-w-[300px] truncate">{row.getValue('course')}</div>,
  },
  {
    accessorKey: 'status',
    header: 'Status',
    cell: ({ row }) => {
      const status = row.getValue('status');
      return <Badge variant={getStatusVariant(status)}>{status}</Badge>;
    },
  },
  {
    accessorKey: 'dateenrolled',
    header: ({ column }) => {
      return (
        <Button
          variant="ghost"
          onClick={() => column.toggleSorting(column.getIsSorted() === 'asc')}
        >
          Date Enrolled
          <ArrowUpDown className="ml-2 h-4 w-4" />
        </Button>
      );
    },
    cell: ({ row }) => {
      const date = row.getValue('dateenrolled');
      return date ? new Date(date).toLocaleDateString() : 'N/A';
    },
  },
];

// Export function to get columns based on level
export function getEnrollmentColumns(level) {
  switch (level) {
    case 'gradeschool':
      return gradeSchoolColumns;
    case 'shs':
      return shsColumns;
    case 'college':
      return collegeColumns;
    default:
      return commonColumns;
  }
}
