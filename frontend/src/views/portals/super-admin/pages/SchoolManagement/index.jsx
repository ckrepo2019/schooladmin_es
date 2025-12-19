import { useState, useEffect } from 'react'
import { toast } from 'sonner'
import { Loader2 } from 'lucide-react'
import { SchoolsProvider } from './components/schools-provider'
import { SchoolsTable } from './components/schools-table'
import { SchoolsDialogs } from './components/schools-dialogs'
import { SchoolsPrimaryButtons } from './components/schools-primary-buttons'

export default function SchoolManagement() {
  const [schools, setSchools] = useState([])
  const [isLoading, setIsLoading] = useState(true)

  const fetchSchools = async () => {
    try {
      const token = localStorage.getItem('token')
      const response = await fetch('http://localhost:5000/api/super-admin/schools', {
        method: 'GET',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`,
        },
        credentials: 'include',
      })

      const data = await response.json()

      if (response.ok) {
        setSchools(data.data || [])
      } else {
        toast.error(data.message || 'Failed to fetch schools')
      }
    } catch (error) {
      toast.error('An error occurred while fetching schools')
    } finally {
      setIsLoading(false)
    }
  }

  useEffect(() => {
    fetchSchools()
  }, [])

  if (isLoading) {
    return (
      <div className='flex h-[450px] items-center justify-center'>
        <Loader2 className='h-8 w-8 animate-spin text-muted-foreground' />
      </div>
    )
  }

  return (
    <SchoolsProvider>
      <div className='space-y-6'>
        <div className='flex items-center justify-between'>
          <div>
            <h2 className='text-2xl font-bold tracking-tight'>School Management</h2>
            <p className='text-muted-foreground'>
              Manage school database configurations and connections.
            </p>
          </div>
          <SchoolsPrimaryButtons />
        </div>
        <SchoolsTable data={schools} />
        <SchoolsDialogs onSuccess={fetchSchools} />
      </div>
    </SchoolsProvider>
  )
}
