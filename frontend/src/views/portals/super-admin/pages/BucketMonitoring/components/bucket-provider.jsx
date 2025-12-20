import { createContext, useContext, useState } from 'react'

const BucketContext = createContext(undefined)

export function BucketProvider({ children }) {
  const [open, setOpen] = useState(null) // 'preview' | null
  const [currentFile, setCurrentFile] = useState(null) // Selected file object

  return (
    <BucketContext.Provider
      value={{
        open,
        setOpen,
        currentFile,
        setCurrentFile,
      }}
    >
      {children}
    </BucketContext.Provider>
  )
}

export const useBucket = () => {
  const context = useContext(BucketContext)
  if (!context) {
    throw new Error('useBucket must be used within BucketProvider')
  }
  return context
}
