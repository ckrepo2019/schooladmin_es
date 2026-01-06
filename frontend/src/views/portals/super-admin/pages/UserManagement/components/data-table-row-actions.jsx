import { MoreHorizontal, Pen, Trash2, KeyRound, LogIn } from 'lucide-react'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { useNavigate } from 'react-router-dom'
import { toast } from 'sonner'
import { apiUrl } from '@/lib/api'
import { useUsers } from './users-provider'

export function DataTableRowActions({ row }) {
  const { setOpen, setCurrentRow } = useUsers()
  const navigate = useNavigate()

  const handleEdit = () => {
    setCurrentRow(row.original)
    setOpen('edit')
  }

  const handleChangePassword = () => {
    setCurrentRow(row.original)
    setOpen('password')
  }

  const handleDelete = () => {
    setCurrentRow(row.original)
    setOpen('delete')
  }

  const handleSwitchAccount = async () => {
    const token = localStorage.getItem('token')
    const superAdminUser = JSON.parse(localStorage.getItem('user') || '{}')

    if (!token || superAdminUser.role !== 'super-admin') {
      toast.error('Switch account is only available for super admins.')
      return
    }

    try {
      const response = await fetch(
        apiUrl(`/api/super-admin/users/${row.original.id}/switch`),
        {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            Authorization: `Bearer ${token}`,
          },
          credentials: 'include',
        }
      )

      const result = await response.json()

      if (response.ok && result.status === 'success') {
        localStorage.setItem(
          'superadmin_session',
          JSON.stringify({ token, user: superAdminUser })
        )
        localStorage.setItem('impersonation_active', 'true')
        localStorage.setItem('token', result.data.token)
        localStorage.setItem('user', JSON.stringify(result.data.user))
        localStorage.removeItem('selectedSchool')

        toast.success('Switched account', {
          description: `Now logged in as ${result.data.user.username}.`,
        })

        navigate('/admin/select-school', { replace: true })
      } else {
        toast.error(result.message || 'Failed to switch account')
      }
    } catch (error) {
      console.error('Switch account error:', error)
      toast.error('Failed to switch account')
    }
  }

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button
          variant='ghost'
          className='flex h-8 w-8 p-0 data-[state=open]:bg-muted'
        >
          <MoreHorizontal className='h-4 w-4' />
          <span className='sr-only'>Open menu</span>
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align='end' className='w-[160px]'>
        <DropdownMenuItem onClick={handleEdit}>
          <Pen className='mr-2 h-3.5 w-3.5 text-muted-foreground/70' />
          Edit
        </DropdownMenuItem>
        <DropdownMenuItem onClick={handleChangePassword}>
          <KeyRound className='mr-2 h-3.5 w-3.5 text-muted-foreground/70' />
          Change Password
        </DropdownMenuItem>
        <DropdownMenuItem onClick={handleSwitchAccount}>
          <LogIn className='mr-2 h-3.5 w-3.5 text-muted-foreground/70' />
          Switch Account
        </DropdownMenuItem>
        <DropdownMenuSeparator />
        <DropdownMenuItem onClick={handleDelete} className='text-destructive'>
          <Trash2 className='mr-2 h-3.5 w-3.5' />
          Delete
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  )
}
