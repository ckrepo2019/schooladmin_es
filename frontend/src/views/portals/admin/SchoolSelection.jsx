import { useState, useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import { toast } from 'sonner'
import { Loader2, School, ChevronRight, MapPin } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { apiUrl } from '@/lib/api'
import ckLogo from '@/assets/ck-logo.png'
import { setFavicon } from '@/lib/metadata'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'

export default function SchoolSelection() {
  const navigate = useNavigate()
  const [schools, setSchools] = useState([])
  const [isLoading, setIsLoading] = useState(true)
  const user = JSON.parse(localStorage.getItem('user') || '{}')
  const appTitle = import.meta.env.VITE_APP_TITLE || 'School Admin'

  useEffect(() => {
    fetchUserSchools()
  }, [])

  useEffect(() => {
    document.title = `Select School | ${appTitle}`
    setFavicon(ckLogo)
  }, [appTitle])

  const fetchUserSchools = async () => {
    try {
      const token = localStorage.getItem('token')
      const response = await fetch(apiUrl('/api/auth/me'), {
        method: 'GET',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`,
        },
        credentials: 'include',
      })

      const data = await response.json()

      if (response.ok) {
        setSchools(data.data.schools || [])

        // If user has no schools assigned, show error and logout
        if (!data.data.schools || data.data.schools.length === 0) {
          toast.error('No schools assigned to your account. Please contact super admin.')
          setTimeout(() => {
            localStorage.removeItem('token')
            localStorage.removeItem('user')
            navigate('/login')
          }, 2000)
        }
      } else {
        toast.error(data.message || 'Failed to fetch schools')
      }
    } catch (error) {
      toast.error('An error occurred while fetching schools')
    } finally {
      setIsLoading(false)
    }
  }

  const handleSelectSchool = (school) => {
    // Store selected school in localStorage
    localStorage.setItem('selectedSchool', JSON.stringify(school))

    // Redirect to admin dashboard
    navigate('/admin/dashboard')
    toast.success(`Switched to ${school.school_name}`)
  }

  const handleLogout = () => {
    localStorage.removeItem('token')
    localStorage.removeItem('user')
    localStorage.removeItem('selectedSchool')
    navigate('/login')
  }

  if (isLoading) {
    return (
      <div className='flex min-h-screen items-center justify-center'>
        <Loader2 className='h-8 w-8 animate-spin text-muted-foreground' />
      </div>
    )
  }

  return (
    <div className='min-h-screen bg-background'>
      <div className='container mx-auto px-4 py-8'>
        <div className='mb-8 flex items-center justify-between'>
          <div>
            <h1 className='text-3xl font-bold tracking-tight'>Select School</h1>
            <p className='text-muted-foreground mt-2'>
              Welcome back, <span className='font-semibold'>{user.username}</span>. Please select a school to continue.
            </p>
          </div>
          <Button variant='outline' onClick={handleLogout}>
            Logout
          </Button>
        </div>

        <div className='grid gap-4 md:grid-cols-2 lg:grid-cols-3'>
          {schools.map((school) => (
            <Card
              key={school.id}
              className='cursor-pointer transition-all hover:shadow-lg hover:border-primary'
              onClick={() => handleSelectSchool(school)}
            >
              <CardHeader>
                <div className='flex items-start justify-between'>
                  <div className='flex items-center gap-3'>
                    {school.image_logo ? (
                      <div className='flex h-12 w-12 items-center justify-center rounded-lg overflow-hidden bg-muted'>
                        <img
                          src={apiUrl(school.image_logo)}
                          alt={school.school_name}
                          className='h-full w-full object-contain'
                          onError={(e) => {
                            e.target.style.display = 'none'
                            e.target.parentElement.innerHTML = '<div class="flex h-12 w-12 items-center justify-center rounded-lg bg-primary/10"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-6 w-6 text-primary"><path d="M14 22v-4a2 2 0 1 0-4 0v4"/><path d="m18 10 4 2v8a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2v-8l4-2"/><path d="M18 5v17"/><path d="m4 6 8-4 8 4"/><path d="M6 5v17"/><circle cx="12" cy="9" r="2"/></svg></div>'
                          }}
                        />
                      </div>
                    ) : (
                      <div className='flex h-12 w-12 items-center justify-center rounded-lg bg-primary/10'>
                        <School className='h-6 w-6 text-primary' />
                      </div>
                    )}
                    <div>
                      <CardTitle className='text-lg'>{school.school_name}</CardTitle>
                      {school.abbrv && (
                        <CardDescription className='mt-1'>
                          {school.abbrv}
                        </CardDescription>
                      )}
                    </div>
                  </div>
                  <ChevronRight className='h-5 w-5 text-muted-foreground' />
                </div>
              </CardHeader>
              {school.address && (
                <CardContent>
                  <div className='flex items-start gap-2 text-sm'>
                    <MapPin className='h-4 w-4 text-muted-foreground mt-0.5 flex-shrink-0' />
                    <span className='text-muted-foreground line-clamp-2'>
                      {school.address}
                    </span>
                  </div>
                </CardContent>
              )}
            </Card>
          ))}
        </div>

        {schools.length === 0 && !isLoading && (
          <Card className='border-dashed'>
            <CardContent className='flex flex-col items-center justify-center py-12'>
              <School className='h-12 w-12 text-muted-foreground mb-4' />
              <p className='text-muted-foreground text-center'>
                No schools assigned to your account.
                <br />
                Please contact the super administrator.
              </p>
            </CardContent>
          </Card>
        )}
      </div>
    </div>
  )
}
