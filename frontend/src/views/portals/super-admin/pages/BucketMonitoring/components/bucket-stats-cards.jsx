import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'

export function BucketStatsCards({ stats }) {
  if (!stats) return null

  return (
    <div className='grid gap-4 md:grid-cols-3 lg:grid-cols-4'>
      {/* Total Files Card */}
      <Card data-watermark='FILES'>
        <CardHeader className='pb-2'>
          <CardTitle className='text-sm font-medium'>Total Files</CardTitle>
        </CardHeader>
        <CardContent>
          <div className='text-2xl font-bold'>{stats.totalFiles}</div>
        </CardContent>
      </Card>

      {/* Total Storage Card */}
      <Card data-watermark='STOR'>
        <CardHeader className='pb-2'>
          <CardTitle className='text-sm font-medium'>Total Storage</CardTitle>
        </CardHeader>
        <CardContent>
          <div className='text-2xl font-bold'>{stats.totalSizeFormatted}</div>
        </CardContent>
      </Card>

      {/* Folders Card */}
      <Card data-watermark='FOLD'>
        <CardHeader className='pb-2'>
          <CardTitle className='text-sm font-medium'>Folders</CardTitle>
        </CardHeader>
        <CardContent>
          <div className='text-2xl font-bold'>{stats.folderBreakdown?.length || 0}</div>
        </CardContent>
      </Card>

      {/* Folder Breakdown Card */}
      <Card className='md:col-span-3 lg:col-span-1' data-watermark='DIR'>
        <CardHeader>
          <CardTitle className='text-sm font-medium'>By Folder</CardTitle>
        </CardHeader>
        <CardContent>
          <div className='space-y-2'>
            {stats.folderBreakdown?.slice(0, 3).map((folder) => (
              <div key={folder.folder} className='flex justify-between text-xs'>
                <span className='truncate'>{folder.folder}</span>
                <span className='text-muted-foreground'>{folder.sizeFormatted}</span>
              </div>
            ))}
            {(!stats.folderBreakdown || stats.folderBreakdown.length === 0) && (
              <p className='text-xs text-muted-foreground'>No folders yet</p>
            )}
          </div>
        </CardContent>
      </Card>
    </div>
  )
}
