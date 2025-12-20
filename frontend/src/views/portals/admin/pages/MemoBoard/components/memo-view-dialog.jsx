import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Download, FileText, File } from 'lucide-react'

// Helper to check file type from URL
const getFileType = (url) => {
  const fileName = url.split('/').pop()
  const ext = fileName.split('.').pop()?.toLowerCase()
  const imageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg']
  if (imageExts.includes(ext)) return 'image'
  if (ext === 'pdf') return 'pdf'
  if (ext === 'docx') return 'docx'
  return 'unknown'
}

export function MemoViewDialog({ memo, open, onClose }) {
  if (!memo) return null

  const userIds = memo.user_ids
  const isAllUsers = !userIds || userIds.length === 0

  // Fix URLs that are missing protocol (legacy issue)
  let contentUrl = memo.content
  if (contentUrl && contentUrl.includes('vultrobjects.com') && !contentUrl.startsWith('http')) {
    contentUrl = `https://${contentUrl}`
  }

  // Check if content is a file URL or legacy HTML
  const isFileUrl = contentUrl?.startsWith('http://') || contentUrl?.startsWith('https://')

  return (
    <Dialog open={open} onOpenChange={onClose}>
      <DialogContent className='max-w-3xl max-h-[80vh] overflow-y-auto'>
        <DialogHeader>
          <DialogTitle>{memo.title}</DialogTitle>
          <DialogDescription asChild>
            <div className='flex items-center gap-2 mt-2'>
              <span>By {memo.creator_name}</span>
              <span className='text-muted-foreground'>{'\u2022'}</span>
              <span>{new Date(memo.created_at).toLocaleDateString()}</span>
              <span className='text-muted-foreground'>{'\u2022'}</span>
              <Badge variant={isAllUsers ? 'default' : 'secondary'} className='text-xs'>
                {isAllUsers ? 'All Employees' : `${userIds.length} Employees`}
              </Badge>
            </div>
          </DialogDescription>
        </DialogHeader>

        {/* Content Display */}
        {contentUrl && (
          <>
            {isFileUrl ? (
              // New: File-based memo
              (() => {
                const fileType = getFileType(contentUrl)
                const fileName = contentUrl.split('/').pop()

                if (fileType === 'image') {
                  return (
                    <div className='flex justify-center'>
                      <img
                        src={contentUrl}
                        alt='Memo attachment'
                        className='max-w-full max-h-96 rounded-lg'
                      />
                    </div>
                  )
                } else if (fileType === 'pdf') {
                  return (
                    <div className='space-y-2'>
                      <div className='flex items-center justify-between p-2 bg-muted rounded-lg'>
                        <div className='flex items-center space-x-2'>
                          <FileText className='h-5 w-5 text-muted-foreground' />
                          <p className='font-medium text-sm'>{fileName}</p>
                        </div>
                        <Button
                          variant='outline'
                          size='sm'
                          onClick={() => window.open(contentUrl, '_blank')}
                        >
                          <Download className='h-4 w-4 mr-2' />
                          Download
                        </Button>
                      </div>
                      <div className='border rounded-lg overflow-hidden'>
                        <iframe
                          src={contentUrl}
                          className='w-full h-[600px]'
                          title='PDF Preview'
                        />
                      </div>
                    </div>
                  )
                } else if (fileType === 'docx') {
                  return (
                    <div className='space-y-2'>
                      <div className='flex items-center justify-between p-2 bg-muted rounded-lg'>
                        <div className='flex items-center space-x-2'>
                          <File className='h-5 w-5 text-muted-foreground' />
                          <p className='font-medium text-sm'>{fileName}</p>
                        </div>
                        <Button
                          variant='outline'
                          size='sm'
                          onClick={() => window.open(contentUrl, '_blank')}
                        >
                          <Download className='h-4 w-4 mr-2' />
                          Download
                        </Button>
                      </div>
                      <div className='border rounded-lg overflow-hidden'>
                        <iframe
                          src={`https://view.officeapps.live.com/op/embed.aspx?src=${encodeURIComponent(contentUrl)}`}
                          className='w-full h-[600px]'
                          title='Document Preview'
                        />
                      </div>
                    </div>
                  )
                } else {
                  return (
                    <div className='flex items-center justify-between p-4 bg-muted rounded-lg'>
                      <div className='flex items-center space-x-3'>
                        <File className='h-8 w-8 text-muted-foreground' />
                        <div>
                          <p className='font-medium'>{fileName}</p>
                          <p className='text-sm text-muted-foreground'>
                            {fileType.toUpperCase()} document
                          </p>
                        </div>
                      </div>
                      <Button
                        variant='outline'
                        size='sm'
                        onClick={() => window.open(contentUrl, '_blank')}
                      >
                        <Download className='h-4 w-4 mr-2' />
                        Download
                      </Button>
                    </div>
                  )
                }
              })()
            ) : (
              // Legacy: HTML content
              <div
                className='prose prose-sm max-w-none dark:prose-invert'
                dangerouslySetInnerHTML={{ __html: contentUrl }}
              />
            )}
          </>
        )}
      </DialogContent>
    </Dialog>
  )
}

