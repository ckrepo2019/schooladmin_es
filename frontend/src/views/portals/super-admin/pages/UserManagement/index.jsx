import { useState, useEffect } from 'react'
import { toast } from 'sonner'
import { Loader2 } from 'lucide-react'
import { apiUrl } from '@/lib/api'
import { UsersProvider } from './components/users-provider'
import { UsersTable } from './components/users-table'
import { UsersDialogs } from './components/users-dialogs'
import { UsersPrimaryButtons } from './components/users-primary-buttons'

export default function UserManagement() {
  const [users, setUsers] = useState([])
  const [isLoading, setIsLoading] = useState(true)

  const fetchUsers = async () => {
    try {
      const token = localStorage.getItem('token')
      const response = await fetch(apiUrl('/api/super-admin/users'), {
        method: 'GET',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`,
        },
        credentials: 'include',
      })

      const data = await response.json()

      if (response.ok) {
        setUsers(data.data || [])
      } else {
        toast.error(data.message || 'Failed to fetch users')
      }
    } catch (error) {
      toast.error('An error occurred while fetching users')
    } finally {
      setIsLoading(false)
    }
  }

  useEffect(() => {
    fetchUsers()
  }, [])

  if (isLoading) {
    return (
      <div className='flex h-[450px] items-center justify-center'>
        <Loader2 className='h-8 w-8 animate-spin text-muted-foreground' />
      </div>
    )
  }

  return (
    <UsersProvider>
      <div className='space-y-6'>
        <div className='flex items-center justify-between'>
          <div>
            <h2 className='text-2xl font-bold tracking-tight'>User Management</h2>
            <p className='text-muted-foreground'>
              Manage users and their school assignments.
            </p>
          </div>
          <UsersPrimaryButtons />
        </div>
        <UsersTable data={users} />
        <UsersDialogs onSuccess={fetchUsers} />
      </div>
    </UsersProvider>
  )
}
