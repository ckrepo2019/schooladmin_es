import { useBucket } from './bucket-provider'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Download, ExternalLink } from 'lucide-react'
import { Badge } from '@/components/ui/badge'

// Helper to check if file is image
const isImageFile = (filename) => {
  const ext = filename.split('.').pop()?.toLowerCase()
  return ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'].includes(ext)
}

// Helper to format file size
const formatFileSize = (bytes) => {
  if (bytes === 0) return '0 Bytes'
  const k = 1024
  const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]
}

export function BucketPreviewDialog() {
  const { open, setOpen, currentFile } = useBucket()

  if (!currentFile) return null

  const isImage = isImageFile(currentFile.filename)

  const handleDownload = () => {
    window.open(currentFile.url, '_blank')
  }

  const handleOpenUrl = () => {
    window.open(currentFile.url, '_blank')
  }

  return (
    <Dialog open={open === 'preview'} onOpenChange={(isOpen) => setOpen(isOpen ? 'preview' : null)}>
      <DialogContent className='sm:max-w-[700px]'>
        <DialogHeader>
          <DialogTitle>File Preview</DialogTitle>
          <DialogDescription>
            {currentFile.filename}
          </DialogDescription>
        </DialogHeader>

        {/* File Preview Section */}
        <div className='space-y-4'>
          {/* Image Preview */}
          {isImage ? (
            <div className='flex justify-center items-center bg-muted rounded-lg p-4 min-h-[300px]'>
              <img
                src={currentFile.url}
                alt={currentFile.filename}
                className='max-h-[400px] max-w-full object-contain'
                onError={(e) => {
                  e.target.src = 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg"/>'
                  e.target.alt = 'Failed to load image'
                }}
              />
            </div>
          ) : (
            <div className='flex flex-col justify-center items-center bg-muted rounded-lg p-8 min-h-[200px] space-y-2'>
              <p className='text-muted-foreground'>Preview not available for this file type</p>
              <Badge variant='secondary'>{currentFile.filename.split('.').pop()?.toUpperCase()}</Badge>
            </div>
          )}

          {/* File Details */}
          <div className='grid grid-cols-2 gap-4 text-sm'>
            <div>
              <p className='font-medium text-muted-foreground'>Folder</p>
              <p>{currentFile.folder}</p>
            </div>
            <div>
              <p className='font-medium text-muted-foreground'>Size</p>
              <p>{formatFileSize(currentFile.size)}</p>
            </div>
            <div>
              <p className='font-medium text-muted-foreground'>Upload Date</p>
              <p>{new Date(currentFile.lastModified).toLocaleString()}</p>
            </div>
            <div>
              <p className='font-medium text-muted-foreground'>Type</p>
              <p>{currentFile.filename.split('.').pop()?.toUpperCase()}</p>
            </div>
          </div>

          {/* Action Buttons */}
          <div className='flex justify-end space-x-2'>
            <Button variant='outline' onClick={handleOpenUrl}>
              <ExternalLink className='mr-2 h-4 w-4' />
              Open URL
            </Button>
            <Button onClick={handleDownload}>
              <Download className='mr-2 h-4 w-4' />
              Download
            </Button>
          </div>
        </div>
      </DialogContent>
    </Dialog>
  )
}
