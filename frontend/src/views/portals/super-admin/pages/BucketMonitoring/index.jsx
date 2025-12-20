import { useState, useEffect } from 'react'
import { toast } from 'sonner'
import { Loader2 } from 'lucide-react'
import { apiUrl } from '@/lib/api'
import { BucketProvider } from './components/bucket-provider'
import { BucketStatsCards } from './components/bucket-stats-cards'
import { BucketTable } from './components/bucket-table'
import { BucketPreviewDialog } from './components/bucket-preview-dialog'

export default function BucketMonitoring() {
  const [files, setFiles] = useState([])
  const [stats, setStats] = useState(null)
  const [isLoading, setIsLoading] = useState(true)

  const fetchData = async () => {
    try {
      const token = localStorage.getItem('token')

      // Fetch files and stats in parallel
      const [filesResponse, statsResponse] = await Promise.all([
        fetch(apiUrl('/api/super-admin/bucket/files'), {
          method: 'GET',
          headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${token}`,
          },
          credentials: 'include',
        }),
        fetch(apiUrl('/api/super-admin/bucket/stats'), {
          method: 'GET',
          headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${token}`,
          },
          credentials: 'include',
        }),
      ])

      const filesData = await filesResponse.json()
      const statsData = await statsResponse.json()

      if (filesResponse.ok) {
        setFiles(filesData.data || [])
      } else {
        toast.error(filesData.message || 'Failed to fetch files')
      }

      if (statsResponse.ok) {
        setStats(statsData.data || null)
      } else {
        toast.error(statsData.message || 'Failed to fetch statistics')
      }
    } catch (error) {
      toast.error('An error occurred while fetching bucket data')
    } finally {
      setIsLoading(false)
    }
  }

  useEffect(() => {
    fetchData()
  }, [])

  if (isLoading) {
    return (
      <div className='flex h-[450px] items-center justify-center'>
        <Loader2 className='h-8 w-8 animate-spin text-muted-foreground' />
      </div>
    )
  }

  return (
    <BucketProvider>
      <div className='space-y-6'>
        <div className='flex items-center justify-between'>
          <div>
            <h2 className='text-2xl font-bold tracking-tight'>Bucket Monitoring</h2>
            <p className='text-muted-foreground'>
              View and manage files in object storage.
            </p>
          </div>
        </div>
        <BucketStatsCards stats={stats} />
        <BucketTable data={files} />
        <BucketPreviewDialog />
      </div>
    </BucketProvider>
  )
}
