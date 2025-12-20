import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'

export default function HelpCenter() {
  return (
    <div className='space-y-6'>
      <div>
        <h2 className='text-2xl font-bold tracking-tight'>Help Center</h2>
        <p className='text-muted-foreground'>Get help and find answers.</p>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Coming Soon</CardTitle>
          <CardDescription>
            Help articles and support links will be available here.
          </CardDescription>
        </CardHeader>
        <CardContent className='text-sm text-muted-foreground'>
          If you need assistance, please contact your system administrator.
        </CardContent>
      </Card>
    </div>
  )
}

