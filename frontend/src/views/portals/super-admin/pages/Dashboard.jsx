import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'

export default function SuperAdminDashboard() {
  const user = JSON.parse(localStorage.getItem('user') || '{}')

  return (
    <div className='space-y-6'>
      <div>
        <h1 className='text-3xl font-bold tracking-tight'>Dashboard</h1>
        <p className='text-muted-foreground mt-2'>
          Welcome back, {user.username}!
        </p>
      </div>

      <div className='grid gap-4 md:grid-cols-2 lg:grid-cols-4'>
        <Card data-watermark='USERS'>
          <CardHeader className='pb-2'>
            <CardTitle className='text-sm font-medium'>
              Total Users
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className='text-2xl font-bold'>0</div>
            <p className='text-xs text-muted-foreground'>
              Total registered users
            </p>
          </CardContent>
        </Card>

        <Card data-watermark='SESS'>
          <CardHeader className='pb-2'>
            <CardTitle className='text-sm font-medium'>
              Active Sessions
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className='text-2xl font-bold'>1</div>
            <p className='text-xs text-muted-foreground'>
              Currently active users
            </p>
          </CardContent>
        </Card>

        <Card data-watermark='STATUS'>
          <CardHeader className='pb-2'>
            <CardTitle className='text-sm font-medium'>
              System Status
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className='text-2xl font-bold'>Online</div>
            <p className='text-xs text-muted-foreground'>
              All systems operational
            </p>
          </CardContent>
        </Card>

        <Card data-watermark='ROLE'>
          <CardHeader className='pb-2'>
            <CardTitle className='text-sm font-medium'>
              Your Role
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className='text-2xl font-bold capitalize'>
              {user.role?.replace('-', ' ') || 'N/A'}
            </div>
            <p className='text-xs text-muted-foreground'>
              User ID: {user.user_id || 'N/A'}
            </p>
          </CardContent>
        </Card>
      </div>

      <Card data-watermark='ACTION'>
        <CardHeader>
          <CardTitle>Quick Actions</CardTitle>
          <CardDescription>
            Manage your system from here
          </CardDescription>
        </CardHeader>
        <CardContent>
          <p className='text-sm text-muted-foreground'>
            More features coming soon...
          </p>
        </CardContent>
      </Card>
    </div>
  )
}
