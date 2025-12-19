export default function AdminDashboard() {
  const selectedSchool = JSON.parse(localStorage.getItem('selectedSchool') || 'null')

  return (
    <div className='space-y-6'>
      <div>
        <h2 className='text-2xl font-bold tracking-tight'>Dashboard</h2>
        <p className='text-muted-foreground'>
          Welcome to {selectedSchool?.school_name || 'School'} Admin Dashboard
        </p>
      </div>

      <div className='grid gap-4 md:grid-cols-2 lg:grid-cols-4'>
        {/* Placeholder cards */}
        <div className='rounded-lg border bg-card p-6'>
          <h3 className='text-sm font-medium text-muted-foreground'>Total Students</h3>
          <p className='text-2xl font-bold mt-2'>Coming Soon</p>
        </div>
        <div className='rounded-lg border bg-card p-6'>
          <h3 className='text-sm font-medium text-muted-foreground'>Total Teachers</h3>
          <p className='text-2xl font-bold mt-2'>Coming Soon</p>
        </div>
        <div className='rounded-lg border bg-card p-6'>
          <h3 className='text-sm font-medium text-muted-foreground'>Active Classes</h3>
          <p className='text-2xl font-bold mt-2'>Coming Soon</p>
        </div>
        <div className='rounded-lg border bg-card p-6'>
          <h3 className='text-sm font-medium text-muted-foreground'>Pending Tasks</h3>
          <p className='text-2xl font-bold mt-2'>Coming Soon</p>
        </div>
      </div>
    </div>
  )
}
