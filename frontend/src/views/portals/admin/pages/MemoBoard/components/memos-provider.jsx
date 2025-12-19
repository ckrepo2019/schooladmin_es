import { createContext, useContext, useState } from 'react'

const MemosContext = createContext(null)

export function MemosProvider({ children }) {
  const [open, setOpen] = useState(null)
  const [currentRow, setCurrentRow] = useState(null)

  const value = {
    open,
    setOpen,
    currentRow,
    setCurrentRow,
  }

  return (
    <MemosContext.Provider value={value}>
      {children}
    </MemosContext.Provider>
  )
}

export const useMemos = () => {
  const context = useContext(MemosContext)
  if (!context) {
    throw new Error('useMemos must be used within MemosProvider')
  }
  return context
}
