import { ArrowUpDown } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';

const getTapStateVariant = (state) => {
  if (state === 'IN') return 'default';
  if (state === 'OUT') return 'destructive';
  return 'outline';
};

const formatDate = (value) => {
  if (!value) return '-';
  const date = new Date(`${value}T00:00:00`);
  if (Number.isNaN(date.getTime())) return value;
  return date.toLocaleDateString();
};

export const attendanceColumns = [
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
    accessorKey: 'identifier',
    header: ({ column }) => (
      <Button
        variant="ghost"
        onClick={() => column.toggleSorting(column.getIsSorted() === 'asc')}
      >
        Employee ID
        <ArrowUpDown className="ml-2 h-4 w-4" />
      </Button>
    ),
    cell: ({ row }) => {
      const identifier = row.getValue('identifier') || row.original?.person_id;
      return <div className="font-mono text-sm">{identifier || '-'}</div>;
    },
  },
  {
    accessorKey: 'full_name',
    header: ({ column }) => (
      <Button
        variant="ghost"
        onClick={() => column.toggleSorting(column.getIsSorted() === 'asc')}
      >
        Employee Name
        <ArrowUpDown className="ml-2 h-4 w-4" />
      </Button>
    ),
    cell: ({ row }) => <div className="font-medium">{row.getValue('full_name') || 'Unknown'}</div>,
  },
  {
    accessorKey: 'usertype_name',
    header: 'User Type',
    cell: ({ row }) => (
      <Badge variant="outline">{row.getValue('usertype_name') || 'Unknown'}</Badge>
    ),
  },
  {
    accessorKey: 'tapstate',
    header: 'Tap State',
    cell: ({ row }) => {
      const state = row.getValue('tapstate');
      return <Badge variant={getTapStateVariant(state)}>{state || 'N/A'}</Badge>;
    },
  },
  {
    accessorKey: 'tdate',
    header: ({ column }) => (
      <Button
        variant="ghost"
        onClick={() => column.toggleSorting(column.getIsSorted() === 'asc')}
      >
        Date
        <ArrowUpDown className="ml-2 h-4 w-4" />
      </Button>
    ),
    cell: ({ row }) => <div>{formatDate(row.getValue('tdate'))}</div>,
  },
  {
    accessorKey: 'ttime',
    header: 'Time',
    cell: ({ row }) => <div className="font-mono text-sm">{row.getValue('ttime') || '-'}</div>,
  },
  {
    accessorKey: 'station_id',
    header: 'Station',
    cell: ({ row }) => <div>{row.getValue('station_id') || '-'}</div>,
  },
];
